<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Feature;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Name('RequirementCreateTool')]
#[Description(
    'Creates a small user story in a feature and appends it to requirement display order. '
    . 'Read the specification and feature first. '
    . 'Write only the action phrase in name: for Lucia’s Restaurant use take orders and AssignmentCreateTool to attach Waiters, generating Waiters can take orders. '
    . 'Describe observable behaviour, acceptance criteria, motivation and exclusions. '
    . 'The server generates a permanent reference. '
    . 'Drafts may be authored but must not be implemented. '
    . 'Use TaskCreateTool for optional implementation steps and UnknownCreateTool for ambiguities; never invent answers. '
    . 'Provide descriptions as Markdown; the server converts them to stored HTML, limited to 10000 characters. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class RequirementCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'feature_id' => ['required', 'string'],
            'data' => ['required', 'array:name,description,source,blocked_reason,is_draft'],
            'data.name' => ['required', 'string', 'max:250'],
            'data.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'data.source' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.blocked_reason' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.is_draft' => ['sometimes', 'required', 'boolean'],
        ]);
        $data = $validated['data'];
        if (isset($data['description'])) {
            $data['description'] = Str::markdown($data['description'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            Validator::make($data, ['description' => ['max:10000']])->validate();
        }

        try {
            $feature = (new Feature())->resolveRouteBinding($validated['feature_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $feature)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $requirement = DB::transaction(function () use ($feature, $data) {
            $data['weight'] = min(250, ($feature->requirements()->max('weight') ?? -1) + 1);

            return $feature->requirements()->create($data)->refresh();
        });

        $result = $requirement->obfuscateIdentifiers($requirement->attributesToArray());
        unset($result['history'], $result['uuid']);
        $result['description'] = Str::htmlToMarkdown($result['description'] ?? '');

        return Response::structured([...$result, 'is_complete' => $requirement->is_complete]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feature_id' => $schema->string()->description('Opaque ID of the parent feature from a read tool; it must be in an active specification you can edit.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('Only the action phrase, e.g. take orders; up to 250 characters. Never include Waiters can: actor assignments generate the full title. Keep the behaviour small enough to implement in about a day.')->required(),
                'description' => $schema->string()->nullable()->description('Observable behaviour, interactions, acceptance criteria, motivation and explicit exclusions. Preserve established facts and record unresolved questions as unknowns. Provide Markdown; the server converts it to stored HTML, limited to 10000 characters. Raw HTML is stripped. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
                'source' => $schema->string()->nullable()->description('Traceable provenance, e.g. Project kickoff meeting or Email from Jane, 14 Jan 2026; up to 250 characters. Preserve earlier provenance when adding more. Null clears it.'),
                'blocked_reason' => $schema->string()->nullable()->description('A concrete delivery obstacle, e.g. API keys provided by client do not work; up to 250 characters. Use unknowns for unanswered product questions. Null unblocks. Setting a blocker reopens completed work.'),
                'is_draft' => $schema->boolean()->description('True for tentative or future scope; false for actionable requirements. Defaults to false. Specification authors may edit drafts, but agents must not implement drafts or their tasks. Reopen a completed requirement before marking it draft.'),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new requirement only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Requirements;

use App\Actions\Requirements\EditRequirement;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
#[Name('requirement-edit')]
#[Description(
    'Edits only supplied user-story fields; existing assignments, tasks and unknowns remain in place. '
    . 'Read the current requirement first. '
    . 'name is only the action phrase; actor assignments generate its title. '
    . 'Use source to preserve traceable provenance, blocked_reason for concrete obstacles and is_draft for tentative scope. '
    . 'Null blocked_reason unblocks. '
    . 'Completed requirements must be reopened before becoming drafts. '
    . 'Edits can invalidate completion. '
    . 'Use requirement-set-completion for verified completion, and the dedicated assignment/task/unknown tools to change child items. '
    . 'Supplying a description replaces its current content. '
    . 'Provide HTML for descriptions. '
    . 'Include existing content you want to retain. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class RequirementEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $requirement = Requirement::find($request->decodeSqid('id'));

        if (!$requirement || !$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $request->merge([
            'id' => $requirement->id,
            'feature_id' => $requirement->feature_id,
        ]);

        $action = new EditRequirement();
        $validated = $request->validate($action->rules());

        $action->handle($requirement, \Illuminate\Support\Arr::only($validated, [
            'name', 'description', 'source', 'blocked_reason', 'is_draft', 'feature_id',
        ]));

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current requirement from a read tool.')->required(),
            'name' => $schema->string()->description('Only the action phrase, e.g. take orders; up to 250 characters. Never include Waiters can: actor assignments generate the full title. Keep the behaviour small enough to implement in about a day.'),
            'description' => $schema->string()->nullable()->description('Observable behaviour, interactions, acceptance criteria, motivation and explicit exclusions. Preserve established facts and record unresolved questions as unknowns. Provide HTML, limited to 10000 characters. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
            'source' => $schema->string()->nullable()->description('Traceable provenance, e.g. Project kickoff meeting or Email from Jane, 14 Jan 2026; up to 250 characters. Preserve earlier provenance when adding more. Null clears it.'),
            'blocked_reason' => $schema->string()->nullable()->description('A concrete delivery obstacle, e.g. API keys provided by client do not work; up to 250 characters. Use unknowns for unanswered product questions. Null unblocks. Setting a blocker reopens completed work.'),
            'is_draft' => $schema->boolean()->description('True for tentative or future scope; false for actionable requirements. Defaults to false. Specification authors may edit drafts, but agents must not implement drafts or their tasks. Reopen a completed requirement before marking it draft.'),
        ];
    }
}

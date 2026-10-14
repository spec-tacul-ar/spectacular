<?php

namespace App\Mcp\Tools\Features;

use App\Models\Project;
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
#[Name('FeatureCreateTool')]
#[Description(
    'Creates a product capability in a specification and appends it to feature display order. '
    . 'Choose a focused group such as Orders, Stock or User Authentication, broad enough for several related requirements. '
    . 'Explain motivation, boundaries and exclusions. '
    . 'Read the current specification to avoid duplicate capabilities. '
    . 'Use RequirementCreateTool to add its user stories. '
    . 'Provide descriptions as Markdown; the server converts them to stored HTML, limited to 10000 characters. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class FeatureCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'data' => ['required', 'array:name,description'],
            'data.name' => ['required', 'string', 'max:250'],
            'data.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);
        $data = $validated['data'];
        if (isset($data['description'])) {
            $data['description'] = Str::markdown($data['description'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            Validator::make($data, ['description' => ['max:10000']])->validate();
        }

        try {
            $project = (new Project())->resolveRouteBinding($validated['project_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $project)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $feature = DB::transaction(function () use ($project, $data) {
            $data['weight'] = min(250, ($project->features()->max('weight') ?? -1) + 1);

            return $project->features()->create($data)->refresh();
        });

        $result = $feature->obfuscateIdentifiers($feature->attributesToArray());
        unset($result['history'], $result['uuid']);
        $result['description'] = Str::htmlToMarkdown($result['description'] ?? '');

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Opaque ID of the parent project from a read tool; it must be in an active specification you can edit.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A broad but focused product capability that groups related requirements, e.g. Orders, Stock or User Authentication; up to 250 characters.')->required(),
                'description' => $schema->string()->nullable()->description('Motivation, capability boundaries, high-level context and explicit exclusions. Provide Markdown; the server converts it to stored HTML, limited to 10000 characters. Raw HTML is stripped. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new feature only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

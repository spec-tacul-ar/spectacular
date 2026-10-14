<?php

namespace App\Mcp\Tools\Actors;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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
#[Name('ActorCreateTool')]
#[Description(
    'Creates a product user role in a specification and appends it to the role display order. '
    . 'Use plural roles such as Waiters, Managers or API Clients, with a plain-text summary of duties and motivation. '
    . 'These roles describe who uses the product; they are not personas, accounts or collaborators. '
    . 'Read the current specification to avoid duplicates. '
    . 'Use AssignmentCreateTool to attach this role to requirements. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ActorCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'data' => ['required', 'array:name,summary'],
            'data.name' => ['required', 'string', 'max:250'],
            'data.summary' => ['sometimes', 'nullable', 'string', 'max:2500'],
        ]);
        $data = $validated['data'];
        try {
            $project = (new Project())->resolveRouteBinding($validated['project_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $project)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $actor = DB::transaction(function () use ($project, $data) {
            $data['weight'] = min(250, ($project->actors()->max('weight') ?? -1) + 1);

            return $project->actors()->create($data)->refresh();
        });

        $result = $actor->obfuscateIdentifiers($actor->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Opaque ID of the parent project from a read tool; it must be in an active specification you can edit.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A succinct plural product role, e.g. Waiters, Managers, AI Agents or API Clients; up to 250 characters. Avoid individual personas or collaborator names.')->required(),
                'summary' => $schema->string()->nullable()->description('Plain text describing this role’s duties, interactions and motivation; up to 2500 characters. Null clears it.'),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new actor only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

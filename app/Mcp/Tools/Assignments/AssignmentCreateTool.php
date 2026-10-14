<?php

namespace App\Mcp\Tools\Assignments;

use App\Models\Actor;
use App\Models\Requirement;
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
#[Name('AssignmentCreateTool')]
#[Description(
    'Attaches a product user role to a requirement. '
    . 'Both IDs must belong to the same specification. '
    . 'Assignments generate requirement titles: attach Waiters to a requirement named take orders to display Waiters can take orders. '
    . 'They are not task assignments to collaborators or accounts. '
    . 'An already attached role returns its existing assignment without duplicating or rewriting history. '
    . 'AssignmentDeleteTool detaches a role; reattaching after removal creates a new assignment and retains the deleted one in history. '
    . 'Changes can invalidate requirement completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class AssignmentCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'requirement_id' => ['required', 'string'],
            'data' => ['required', 'array:actor_id'],
            'data.actor_id' => ['required', 'string'],
        ]);
        $data = $validated['data'];
        try {
            $requirement = (new Requirement())->resolveRouteBinding($validated['requirement_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        try {
            $actor = (new Actor())->resolveRouteBinding($data['actor_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $actor)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        if ($actor->project_id !== $requirement->project->id) {
            throw ValidationException::withMessages(['data.actor_id' => 'The actor must belong to the same specification.']);
        }

        $assignment = DB::transaction(fn() => $requirement->assignments()->firstOrCreate(['actor_id' => $actor->id])->refresh());

        $result = $assignment->obfuscateIdentifiers($assignment->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->string()->description('Opaque ID of the parent requirement from a read tool; it must be in an active specification you can edit.')->required(),
            'data' => $schema->object([
                'actor_id' => $schema->string()->description('Opaque actor ID from the same specification. This is a product user role, not an account or project collaborator.')->required(),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new assignment only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

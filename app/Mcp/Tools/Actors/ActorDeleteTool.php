<?php

namespace App\Mcp\Tools\Actors;

use App\Models\Actor;
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
#[IsDestructive(true)]
#[IsOpenWorld(false)]
#[Name('ActorDeleteTool')]
#[Description(
    'Soft-deletes a current actor and records its removal while retaining prior revisions. '
    . 'Detach it from all requirements using AssignmentDeleteTool before removal. '
    . 'This deletes a product role, not an account or collaborator. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns the opaque ID and deletion timestamp; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ActorDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string']]);
        try {
            $actor = (new Actor())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $actor)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        if ($actor->assignments()->exists()) {
            throw ValidationException::withMessages(['id' => 'Detach this actor from all requirements before deleting it.']);
        }

        DB::transaction(fn() => $actor->delete());

        return Response::structured(['id' => $actor->sqid, 'deleted_at' => $actor->deleted_at]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current actor to soft-delete.')->required(),
        ];
    }
}

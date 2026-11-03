<?php

namespace App\Mcp\Tools\Actors;

use App\Actions\Actors\DeleteActor;

use App\Models\Actor;
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
#[IsDestructive(true)]
#[IsOpenWorld(false)]
#[Name('actor-delete')]
#[Description(
    'Soft-deletes a current actor and records its removal while retaining prior revisions. '
    . 'Its requirement assignments are also soft-deleted. '
    . 'This deletes a product role, not an account or collaborator. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns success; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ActorDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $actor = Actor::find($request->decodeSqid('id'));

        if (!$actor || !$request->user()->can('delete', $actor)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be deleted.']);
        }

        (new DeleteActor())->handle($actor);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current actor to soft-delete.')->required(),
        ];
    }
}

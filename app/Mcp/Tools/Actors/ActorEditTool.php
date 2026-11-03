<?php

namespace App\Mcp\Tools\Actors;

use App\Actions\Actors\EditActor;
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
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Name('actor-edit')]
#[Description(
    'Edits a product user role’s plural name or plain-text summary of duties and motivation. '
    . 'Read the current role first. '
    . 'Existing requirement assignments are preserved and their generated titles reflect the role name. '
    . 'This does not edit an account, collaborator or persona. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ActorEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $actor = Actor::find($request->decodeSqid('id'));

        if (!$actor || !$request->user()->can('update', $actor)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $action = new EditActor();
        $validated = $request->validate($action->rules());

        $action->handle($actor, $validated);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current actor from a read tool.')->required(),
            'name' => $schema->string()->description('A succinct plural product role, e.g. Waiters, Managers, AI Agents or API Clients; up to 250 characters. Avoid individual personas or collaborator names.'),
            'summary' => $schema->string()->nullable()->description('Plain text describing this role’s duties, interactions and motivation; up to 2500 characters. Null clears it.'),
        ];
    }
}

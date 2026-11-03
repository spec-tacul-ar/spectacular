<?php

namespace App\Mcp\Tools\Actors;

use App\Actions\Actors\CreateActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
#[Name('actor-create')]
#[Description(
    'Creates a product user role in a specification and appends it to the role display order. '
    . 'Use plural roles such as Waiters, Managers or API Clients, with a plain-text summary of duties and motivation. '
    . 'These roles describe who uses the product; they are not personas, accounts or collaborators. '
    . 'Read the current specification to avoid duplicates. '
    . 'Use assignment-create to attach this role to requirements. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ActorCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->merge(['project_id' => $request->decodeSqid('project_id')]);

        $action = new CreateActor();

        $validated = $request->validate($action->rules());

        $actor = $action->handle($validated);

        return Response::structured(['success' => true, 'id' => $actor->sqid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Opaque ID of the parent project from a read tool; it must be in an active specification you can edit.')->required(),
            'name' => $schema->string()->description('A succinct plural product role, e.g. Waiters, Managers, AI Agents or API Clients; up to 250 characters. Avoid individual personas or collaborator names.')->required(),
            'summary' => $schema->string()->nullable()->description('Plain text describing this role’s duties, interactions and motivation; up to 2500 characters. Null clears it.'),
        ];
    }
}

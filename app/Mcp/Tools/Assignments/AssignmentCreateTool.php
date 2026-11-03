<?php

namespace App\Mcp\Tools\Assignments;

use App\Actions\Assignments\CreateAssignment;
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
#[Name('assignment-create')]
#[Description(
    'Attaches a product user role to a requirement. '
    . 'Both IDs must belong to the same specification. '
    . 'Assignments generate requirement titles: attach Waiters to a requirement named take orders to display Waiters can take orders. '
    . 'They are not task assignments to collaborators or accounts. '
    . 'An already attached role returns its existing assignment ID without duplicating or rewriting history. '
    . 'assignment-delete detaches a role; reattaching after removal creates a new assignment and retains the deleted one in history. '
    . 'Changes can invalidate requirement completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class AssignmentCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->merge([
            'requirement_id' => $request->decodeSqid('requirement_id'),
            'actor_id' => $request->decodeSqid('actor_id'),
        ]);

        $action = new CreateAssignment();
        $validated = $request->validate($action->rules());
        $assignment = $action->handle($validated);

        return Response::structured(['success' => true, 'id' => $assignment->sqid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->string()->description('Opaque ID of the parent requirement from a read tool; it must be in an active specification you can edit.')->required(),
            'actor_id' => $schema->string()->description('Opaque actor ID from the same specification. This is a product user role, not an account or project collaborator.')->required(),
        ];
    }
}

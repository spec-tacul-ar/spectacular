<?php

namespace App\Mcp\Tools\Assignments;

use App\Actions\Assignments\DeleteAssignment;

use App\Models\Assignment;
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
#[Name('assignment-delete')]
#[Description(
    'Soft-deletes a current assignment and records its removal while retaining prior revisions. '
    . 'Detaches a product user role from its requirement, updating the generated title. '
    . 'It does not delete the actor, account or collaborator. '
    . 'Removal can invalidate requirement completion. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns success; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class AssignmentDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $assignment = Assignment::find($request->decodeSqid('id'));

        if (!$assignment || !$request->user()->can('delete', $assignment)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be deleted.']);
        }

        (new DeleteAssignment())->handle($assignment);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current assignment to soft-delete.')->required(),
        ];
    }
}

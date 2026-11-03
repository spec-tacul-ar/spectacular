<?php

namespace App\Mcp\Tools\Requirements;

use App\Actions\Requirements\DeleteRequirement;

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
#[IsDestructive(true)]
#[IsOpenWorld(false)]
#[Name('requirement-delete')]
#[Description(
    'Soft-deletes a current requirement and records its removal while retaining prior revisions. '
    . 'Its assignments, tasks and unknowns are also soft-deleted. '
    . 'Review the full affected scope before removing a requirement. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns success; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class RequirementDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $requirement = Requirement::find($request->decodeSqid('id'));

        if (!$requirement || !$request->user()->can('delete', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be deleted.']);
        }

        (new DeleteRequirement())->handle($requirement);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current requirement to soft-delete.')->required(),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Unknowns;

use App\Actions\Unknowns\CreateUnknown;
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
#[Name('unknown-create')]
#[Description(
    'Creates a precise unanswered product question under a requirement. '
    . 'Read the parent requirement first and avoid duplicate questions. '
    . 'Unknowns document ambiguities to resolve before implementation; use blocked_reason on the requirement for concrete delivery obstacles. '
    . 'Do not invent answers. '
    . 'Use unknown-resolve when the user or established evidence provides a clarification. '
    . 'Adding an unknown changes parent activity and can invalidate completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class UnknownCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->merge([
            'requirement_id' => $request->decodeSqid('requirement_id'),
        ]);

        $action = new CreateUnknown();
        $validated = $request->validate($action->rules());
        $unknown = $action->handle($validated);

        return Response::structured(['success' => true, 'id' => $unknown->sqid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->string()->description('Opaque ID of the parent requirement from a read tool; it must be in an active specification you can edit.')->required(),
            'name' => $schema->string()->description('A precise unanswered product question, e.g. Can an order include multiple tables?; up to 250 characters. Unknowns capture ambiguities, whereas blockers record concrete delivery obstacles.')->required(),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Unknowns;

use App\Actions\Unknowns\EditUnknown;
use App\Models\Unknown;
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
#[Name('unknown-edit')]
#[Description(
    'Edits the wording of an unresolved product question. '
    . 'Keep it precise and preserve established context. '
    . 'This does not record an answer: use unknown-resolve to append an established clarification to the requirement and remove the question atomically. '
    . 'Changes can invalidate requirement completion. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class UnknownEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $unknown = Unknown::find($request->decodeSqid('id'));

        if (!$unknown || !$request->user()->can('update', $unknown)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $action = new EditUnknown();
        $validated = $request->validate($action->rules());

        $action->handle($unknown, $validated);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current unknown from a read tool.')->required(),
            'name' => $schema->string()->description('A precise unanswered product question, e.g. Can an order include multiple tables?; up to 250 characters. Unknowns capture ambiguities, whereas blockers record concrete delivery obstacles.'),
        ];
    }
}

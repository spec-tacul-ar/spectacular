<?php

namespace App\Mcp\Tools\Unknowns;

use App\Actions\Unknowns\DeleteUnknown;

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
#[IsDestructive(true)]
#[IsOpenWorld(false)]
#[Name('unknown-delete')]
#[Description(
    'Soft-deletes a current unknown and records its removal while retaining prior revisions. '
    . 'The parent requirement and its other children remain in place. '
    . 'To record an answer, prefer unknown-resolve, which appends the clarification before removing the question. '
    . 'Removal can invalidate completion. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns success; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class UnknownDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $unknown = Unknown::find($request->decodeSqid('id'));

        if (!$unknown || !$request->user()->can('delete', $unknown)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be deleted.']);
        }

        (new DeleteUnknown())->handle($unknown);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current unknown to soft-delete.')->required(),
        ];
    }
}

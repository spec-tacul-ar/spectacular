<?php

namespace App\Mcp\Tools\Tasks;

use App\Actions\Tasks\DeleteTask;

use App\Models\Task;
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
#[Name('task-delete')]
#[Description(
    'Soft-deletes a current task and records its removal while retaining prior revisions. '
    . 'The parent requirement and its other children remain in place. '
    . 'Removal changes requirement activity and can invalidate completion. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns success; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class TaskDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $task = Task::find($request->decodeSqid('id'));

        if (!$task || !$request->user()->can('delete', $task)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be deleted.']);
        }

        (new DeleteTask())->handle($task);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current task to soft-delete.')->required(),
        ];
    }
}

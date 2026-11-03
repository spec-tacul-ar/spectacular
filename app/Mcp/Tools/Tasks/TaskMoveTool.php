<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Requirement;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
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
#[Name('task-move')]
#[Description(
    'Moves a task into another requirement within the same active specification. '
    . 'Existing IDs, content and revisions are retained; a new revision records the parent change. '
    . 'It appends to destination display order; use task-reorder afterwards. '
    . 'Cross-specification moves are forbidden. '
    . 'Both source and destination require edit access. '
    . 'Moving changes activity on both parents and can invalidate completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class TaskMoveTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'requirement_id' => ['required', 'string'],
        ]);
        $task_id = $request->decodeSqid('id');
        $task = $task_id === null ? null : Task::find($task_id);
        if (!$task) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $task)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $requirement_id = $request->decodeSqid('requirement_id');
        $requirement = $requirement_id === null ? null : Requirement::find($requirement_id);
        if (!$requirement) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        if ($task->project->id !== $requirement->project->id) {
            throw ValidationException::withMessages(['requirement_id' => 'Items can only be moved within the same specification.']);
        }
        $old_requirement = $task->requirement;
        if (!$old_requirement->is($requirement)) {
            DB::transaction(function () use ($task, $requirement, $old_requirement) {
                $task->requirement()->associate($requirement);
                $task->weight = min(250, ($requirement->tasks()->max('weight') ?? -1) + 1);
                $task->save();
                $old_requirement->trackActivity();
            });
        }
        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current task to move.')->required(),
            'requirement_id' => $schema->string()->description('Destination requirement ID in the same specification.')->required(),
        ];
    }
}

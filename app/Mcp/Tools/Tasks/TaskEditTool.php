<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
#[Name('TaskEditTool')]
#[Description(
    'Edits a concrete implementation step or its completion flag. '
    . 'Read its parent requirement before implementing or reporting completion; draft requirements and their tasks must not be implemented. '
    . 'True is_complete means this step was done, not that the requirement was verified. '
    . 'Editing a task can invalidate requirement completion. '
    . 'Use TaskMoveTool to change its requirement and TaskReorderTool for display order. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class TaskEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'data' => ['required', 'array:name,is_complete', 'min:1'],
            'data.name' => ['required', 'sometimes', 'string', 'max:250'],
            'data.is_complete' => ['sometimes', 'required', 'boolean'],
        ]);
        try {
            $task = (new Task())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $task)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        $data = $validated['data'];

        DB::transaction(fn() => $task->update($data));
        $task->refresh();

        $result = $task->obfuscateIdentifiers($task->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current task from a read or create tool.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A concrete implementation step, e.g. Build the order form or Import legacy user data; up to 250 characters. Avoid vague instructions such as Make this work.'),
                'is_complete' => $schema->boolean()->description('True only after this implementation step is done; false reopens it. Defaults to false. Task completion never completes the parent requirement; use RequirementSetCompletionTool for verified requirement completion.'),
            ])->withoutAdditionalProperties()->required()->description('Non-empty partial update. Omitted fields and all child items remain unchanged; null clears nullable fields.'),
        ];
    }
}

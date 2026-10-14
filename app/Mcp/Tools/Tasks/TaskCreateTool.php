<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Requirement;
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
#[Name('TaskCreateTool')]
#[Description(
    'Creates an optional concrete implementation step under a requirement and appends it to task display order. '
    . 'Read the parent requirement, including its draft status, blockers and unknowns, before planning implementation. '
    . 'Use a succinct action such as Build the order form rather than Make this work. '
    . 'Tasks under drafts are tentative and must not be implemented. '
    . 'is_complete tracks this step only and never completes the requirement. '
    . 'Changes can invalidate parent completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class TaskCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'requirement_id' => ['required', 'string'],
            'data' => ['required', 'array:name,is_complete'],
            'data.name' => ['required', 'string', 'max:250'],
            'data.is_complete' => ['sometimes', 'required', 'boolean'],
        ]);
        $data = $validated['data'];
        try {
            $requirement = (new Requirement())->resolveRouteBinding($validated['requirement_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $task = DB::transaction(function () use ($requirement, $data) {
            $data['weight'] = min(250, ($requirement->tasks()->max('weight') ?? -1) + 1);

            return $requirement->tasks()->create($data)->refresh();
        });

        $result = $task->obfuscateIdentifiers($task->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->string()->description('Opaque ID of the parent requirement from a read tool; it must be in an active specification you can edit.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A concrete implementation step, e.g. Build the order form or Import legacy user data; up to 250 characters. Avoid vague instructions such as Make this work.')->required(),
                'is_complete' => $schema->boolean()->description('True only after this implementation step is done; false reopens it. Defaults to false. Task completion never completes the parent requirement; use RequirementSetCompletionTool for verified requirement completion.'),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new task only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

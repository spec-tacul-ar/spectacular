<?php

namespace App\Mcp\Tools\Tasks;

use App\Actions\Tasks\CreateTask;
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
#[Name('task-create')]
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
        $request->merge([
            'requirement_id' => $request->decodeSqid('requirement_id'),
        ]);

        $action = new CreateTask();
        $validated = $request->validate($action->rules());
        $task = $action->handle($validated);

        return Response::structured(['success' => true, 'id' => $task->sqid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->string()->description('Opaque ID of the parent requirement from a read tool; it must be in an active specification you can edit.')->required(),
            'name' => $schema->string()->description('A concrete implementation step, e.g. Build the order form or Import legacy user data; up to 250 characters. Avoid vague instructions such as Make this work.')->required(),
            'is_complete' => $schema->boolean()->description('True only after this implementation step is done; false reopens it. Defaults to false. Task completion never completes the parent requirement; use requirement-set-completion for verified requirement completion.'),
        ];
    }
}

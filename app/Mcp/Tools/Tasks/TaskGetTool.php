<?php

namespace App\Mcp\Tools\Tasks;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('task-get')]
#[Description(
    'Returns one implementation step, including its name, completion flag and parent requirement ID. '
    . 'Task completion does not establish requirement completion. '
    . 'Fetch the parent requirement before acting with requirement-get; do not implement tasks under draft requirements. '
    . 'Consult the full specification for blockers, unresolved unknowns and product acceptance criteria. '
    . 'Supply since to include revisions strictly after an ISO 8601 checkpoint; omit it for current state only. '
    . 'History entries identify their timestamp, author account and AI flag, and contain previous field values. '
    . 'Use project-list-accounts to attribute authors. '
    . 'IDs are opaque tool inputs. '
    . 'This tool is read-only; it cannot alter the specification, feedback or history.',
)]
class TaskGetTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'since' => ['nullable', 'date'],
        ]);

        $task = (new Task())->resolveSoftDeletableRouteBinding($validated['id']);

        if (!$task || !$request->user()->can('view', $task)) {
            return Response::error('This task cannot be found.');
        }

        if (!empty($validated['since'])) {
            $task->loadHistory();
        }

        $data = $task->obfuscateIdentifiers($task->toArray());

        if (!empty($validated['since'])) {
            $history = $task->history
                ->where('timestamp', '>', $validated['since'])
                ->map(fn($revision) => [
                    'timestamp' => $revision['timestamp'],
                    'account_id' => $revision['account_id'] ?? null,
                    'is_ai' => $revision['is_ai'] ?? false,
                    'data' => (clone $task)->forceFill($revision['data'])->obfuscateIdentifiers($revision['data']),
                ])
                ->values();

            $data['history'] = $history;
        }

        return Response::structured($data);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [

            'id' => $schema->string()
                ->description('The opaque task ID from a specification read, create or changes tool.')
                ->required(),

            'since' => $schema->string()
                ->description("An optional ISO 8601 timestamp. When provided, the response includes revisions after this time in history; omit it when only the entity's current state is needed.")
                ->nullable(),

        ];
    }
}

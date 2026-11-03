<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('requirement-get')]
#[Description(
    'Returns one requirement with its action phrase, generated title, description, source, permanent reference, draft status, blocker and activity/completion timestamps. '
    . 'Inspect the current requirement before editing, implementing or reporting progress. '
    . 'Draft requirements and their tasks must not be implemented. '
    . 'Use project-get for complete context, including assignments, tasks and unresolved unknowns. '
    . 'Description fields are returned as stored HTML; write tools accept HTML. '
    . 'Supply since to include revisions strictly after an ISO 8601 checkpoint; omit it for current state only. '
    . 'History entries identify their timestamp, author account and AI flag, and contain previous field values. '
    . 'Use project-list-accounts to attribute authors. '
    . 'IDs are opaque tool inputs. '
    . 'This tool is read-only; it cannot alter the specification, feedback or history.',
)]
class RequirementGetTool extends Tool
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

        $requirement = (new Requirement())->resolveSoftDeletableRouteBinding($validated['id']);

        if (!$requirement || !$request->user()->can('view', $requirement)) {
            return Response::error('This requirement cannot be found.');
        }

        if (!empty($validated['since'])) {
            $requirement->loadHistory();
        }

        $data = $requirement->obfuscateIdentifiers($requirement->toArray());

        if (!empty($validated['since'])) {
            $history = $requirement->history
                ->where('timestamp', '>', $validated['since'])
                ->map(fn($revision) => [
                    'timestamp' => $revision['timestamp'],
                    'account_id' => $revision['account_id'] ?? null,
                    'is_ai' => $revision['is_ai'] ?? false,
                    'data' => (clone $requirement)->forceFill($revision['data'])->obfuscateIdentifiers($revision['data']),
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
                ->description('The opaque requirement ID from a specification read, create or changes tool.')
                ->required(),

            'since' => $schema->string()
                ->description("An optional ISO 8601 timestamp. When provided, the response includes revisions after this time in history; omit it when only the entity's current state is needed.")
                ->nullable(),

        ];
    }
}

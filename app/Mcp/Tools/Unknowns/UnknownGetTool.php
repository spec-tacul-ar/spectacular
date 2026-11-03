<?php

namespace App\Mcp\Tools\Unknowns;

use App\Models\Unknown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('unknown-get')]
#[Description(
    'Returns one unresolved product question, including its wording and parent requirement ID. '
    . 'Fetch the parent requirement before acting with requirement-get and consult the full specification for the relevant product context. '
    . 'Do not invent an answer or implement work under a draft parent. '
    . 'Once the user or established evidence provides an answer, unknown-resolve appends that clarification and removes the question. '
    . 'Supply since to include revisions strictly after an ISO 8601 checkpoint; omit it for current state only. '
    . 'History entries identify their timestamp, author account and AI flag, and contain previous field values. '
    . 'Use project-list-accounts to attribute authors. '
    . 'IDs are opaque tool inputs. '
    . 'This tool is read-only; it cannot alter the specification, feedback or history.',
)]
class UnknownGetTool extends Tool
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

        $unknown = (new Unknown())->resolveSoftDeletableRouteBinding($validated['id']);

        if (!$unknown || !$request->user()->can('view', $unknown)) {
            return Response::error('This unknown cannot be found.');
        }

        if (!empty($validated['since'])) {
            $unknown->loadHistory();
        }

        $data = $unknown->obfuscateIdentifiers($unknown->toArray());

        if (!empty($validated['since'])) {
            $history = $unknown->history
                ->where('timestamp', '>', $validated['since'])
                ->map(fn($revision) => [
                    'timestamp' => $revision['timestamp'],
                    'account_id' => $revision['account_id'] ?? null,
                    'is_ai' => $revision['is_ai'] ?? false,
                    'data' => (clone $unknown)->forceFill($revision['data'])->obfuscateIdentifiers($revision['data']),
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
                ->description('The opaque unknown ID from a specification read, create or changes tool.')
                ->required(),

            'since' => $schema->string()
                ->description("An optional ISO 8601 timestamp. When provided, the response includes revisions after this time in history; omit it when only the entity's current state is needed.")
                ->nullable(),

        ];
    }
}

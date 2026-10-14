<?php

namespace App\Mcp\Tools\Features;

use App\Models\Feature;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('FeatureGetTool')]
#[Description(
    'Returns one capability group, including its name, description and specification ID. '
    . 'Use it to inspect context and boundaries for a changed feature; use ProjectGetTool for its requirements and the full product context. '
    . 'Description fields are returned as stored HTML; write tools accept Markdown and convert it to HTML. '
    . 'Supply since to include revisions strictly after an ISO 8601 checkpoint; omit it for current state only. '
    . 'History entries identify their timestamp, author account and AI flag, and contain previous field values. '
    . 'Use ProjectListAccountsTool to attribute authors. '
    . 'IDs are opaque tool inputs. '
    . 'This tool is read-only; it cannot alter the specification, feedback or history.',
)]
class FeatureGetTool extends Tool
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

        $feature = (new Feature())->resolveSoftDeletableRouteBinding($validated['id']);

        if (!$feature || !$request->user()->can('view', $feature)) {
            return Response::error('This feature cannot be found.');
        }

        if (!empty($validated['since'])) {
            $feature->loadHistory();
        }

        $data = $feature->obfuscateIdentifiers($feature->toArray());

        if (!empty($validated['since'])) {
            $history = $feature->history
                ->where('timestamp', '>', $validated['since'])
                ->map(fn($revision) => [
                    'timestamp' => $revision['timestamp'],
                    'account_id' => $revision['account_id'] ?? null,
                    'is_ai' => $revision['is_ai'] ?? false,
                    'data' => (clone $feature)->forceFill($revision['data'])->obfuscateIdentifiers($revision['data']),
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
                ->description('The opaque feature ID from a specification read, create or changes tool.')
                ->required(),

            'since' => $schema->string()
                ->description("An optional ISO 8601 timestamp. When provided, the response includes revisions after this time in history; omit it when only the entity's current state is needed.")
                ->nullable(),

        ];
    }
}

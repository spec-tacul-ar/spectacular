<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('ProjectGetDetailsTool')]
#[Description(
    'Returns focused project-level context and metadata, including the name, description and language, optionally with project revisions. '
    . 'This complements ProjectGetTool, which returns the complete specification with actors, features, requirements and their child items. '
    . 'Use ProjectGetTool before planning or implementation; use this tool for focused inspection of changed project fields and their history. '
    . 'Description fields are returned as stored HTML; write tools accept Markdown and convert it to HTML. '
    . 'Supply since to include revisions strictly after an ISO 8601 checkpoint; omit it for current state only. '
    . 'History entries identify their timestamp, author account and AI flag, and contain previous field values. '
    . 'Use ProjectListAccountsTool to attribute authors. '
    . 'IDs are opaque tool inputs. '
    . 'This tool is read-only; it cannot alter the specification, feedback or history.',
)]
class ProjectGetDetailsTool extends Tool
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

        $project = (new Project())->resolveSoftDeletableRouteBinding($validated['id']);

        if (!$project || !$request->user()->can('view', $project)) {
            return Response::error('This project cannot be found.');
        }

        if (!empty($validated['since'])) {
            $project->loadHistory();
        }

        $data = $project->obfuscateIdentifiers($project->toArray());

        if (!empty($validated['since'])) {
            $history = $project->history
                ->where('timestamp', '>', $validated['since'])
                ->map(fn($revision) => [
                    'timestamp' => $revision['timestamp'],
                    'account_id' => $revision['account_id'] ?? null,
                    'is_ai' => $revision['is_ai'] ?? false,
                    'data' => (clone $project)->forceFill($revision['data'])->obfuscateIdentifiers($revision['data']),
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
                ->description('The opaque project ID from a specification read, create or changes tool.')
                ->required(),

            'since' => $schema->string()
                ->description("An optional ISO 8601 timestamp. When provided, the response includes revisions after this time in history; omit it when only the entity's current state is needed.")
                ->nullable(),

        ];
    }
}

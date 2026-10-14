<?php

namespace App\Mcp\Tools\Assignments;

use App\Models\Assignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('AssignmentGetTool')]
#[Description(
    'Returns one link between a product user role and a requirement, including actor_id and requirement_id. '
    . 'This is not assignment of implementation work to a collaborator or account. '
    . 'Use ActorGetTool to inspect the role and fetch the parent requirement before acting with RequirementGetTool. '
    . 'Do not implement work for a draft parent requirement; consult the full specification for its blockers and unknowns. '
    . 'Supply since to include revisions strictly after an ISO 8601 checkpoint; omit it for current state only. '
    . 'History entries identify their timestamp, author account and AI flag, and contain previous field values. '
    . 'Use ProjectListAccountsTool to attribute authors. '
    . 'IDs are opaque tool inputs. '
    . 'This tool is read-only; it cannot alter the specification, feedback or history.',
)]
class AssignmentGetTool extends Tool
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

        $assignment = (new Assignment())->resolveSoftDeletableRouteBinding($validated['id']);

        if (!$assignment || !$request->user()->can('view', $assignment)) {
            return Response::error('This assignment cannot be found.');
        }

        if (!empty($validated['since'])) {
            $assignment->loadHistory();
        }

        $data = $assignment->obfuscateIdentifiers($assignment->toArray());

        if (!empty($validated['since'])) {
            $history = $assignment->history
                ->where('timestamp', '>', $validated['since'])
                ->map(fn($revision) => [
                    'timestamp' => $revision['timestamp'],
                    'account_id' => $revision['account_id'] ?? null,
                    'is_ai' => $revision['is_ai'] ?? false,
                    'data' => (clone $assignment)->forceFill($revision['data'])->obfuscateIdentifiers($revision['data']),
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
                ->description('The opaque assignment ID from a specification read, create or changes tool.')
                ->required(),

            'since' => $schema->string()
                ->description("An optional ISO 8601 timestamp. When provided, the response includes revisions after this time in history; omit it when only the entity's current state is needed.")
                ->nullable(),

        ];
    }
}

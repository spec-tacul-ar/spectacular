<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('ProjectGetChangesTool')]
#[Description(
    'Returns changes to a specification after a generated_at checkpoint. Use it to refresh previously fetched context. '
    . 'Existing entities contain only fields changed since that time, new entities are returned in full, and deleted entities contain deleted_at. '
    . 'When a parent is deleted, discard its cached descendants even if they have no individual deletion records in the delta. Feature deletion removes its requirements and their assignments, tasks and unknowns; requirement deletion removes those child items; actor deletion removes its assignments. '
    . 'Changes to draft requirements and their child items are included, but remain non-actionable. Treat the result as a delta, not a complete specification. '
    . 'Before acting on any requirement, task, assignment, or unknown in the result, fetch or consult its parent requirement and re-check draft status, blockers, and unknowns.',
)]
class ProjectGetChangesTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        // TODO Explicit error messages for AI
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'since' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $project = (new Project())->resolveRouteBinding($validated['id']);
        $since = Carbon::parse($validated['since']);

        if (!$project || !$request->user()->can('view', $project)) {
            return Response::error('Project not found.');
        }

        if ($since->isAfter($project->activity_at)) {
            return Response::text('The project has no changes since the provided timestamp.');
        }

        $relations = ['actors', 'assignments', 'features', 'requirements', 'tasks', 'unknowns'];

        $changes = [];

        foreach ($relations as $relation) {
            $changes[$relation] = $project->{$relation}()
                ->withTrashed()
                ->withHistory()
                ->where($relation . '.updated_at', '>', $since)
                ->get()
                ->map(fn($model) => ['id' => $model->sqid, ...$model->getChangesSince($since)])
                ->filter()
                ->values();
        }

        $changes['project'] = ['id' => $project->sqid, ...$project->getChangesSince($since)];

        return Response::structured($changes);
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
                ->description('The specification ID returned by ProjectListTool or ProjectGetTool.')
                ->required(),

            'since' => $schema->string()
                ->description('An ISO 8601 timestamp. Prefer the latest generated_at value returned by ProjectListTool or ProjectGetTool so no changes are missed.')
                ->required(),

        ];
    }
}

<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('GetProjectTool')]
#[Description(
    'Returns the complete current specification: project context, actors, features, requirements, assignments, tasks, blockers, and unknowns. '
    . 'Read it before planning or implementation. A requirement is actionable only when is_draft is false. Draft requirements and their tasks are tentative, may change, and only hint at future direction; do not work on them. '
    . 'Surface relevant blocked_reason and unknowns instead of guessing, and retain generated_at as the checkpoint for GetChangesTool.',
)]
class GetProjectTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        // TODO Explicit error messages for AI
        $validated = $request->validate([
            'id' => ['required', 'string'],
        ]);

        $project = (new Project())->resolveRouteBinding($validated['id']);

        if (!$project || !$request->user()->can('view', $project)) {
            return Response::error('Project not found.');
        }

        $project->load([
            'actors',
            'features',
            'features.requirements',
            'features.requirements.assignments',
            'features.requirements.tasks',
            'features.requirements.unknowns',
        ]);

        return Response::structured([
            'id' => $project->sqid,
            'name' => $project->name,
            'description' => Str::htmlToMarkdown($project->description),
            'actors' => $project->actors->map(fn($actor) => [
                'id' => $actor->sqid,
                'name' => $actor->name,
                'summary' => $actor->summary,
            ]),
            'features' => $project->features->map(fn($feature) => [
                'id' => $feature->sqid,
                'name' => $feature->name,
                'description' => Str::htmlToMarkdown($feature->description),
                'requirements' => $feature->requirements->map(fn($requirement) => [
                    'id' => $requirement->sqid,
                    'title' => $requirement->title,
                    'description' => Str::htmlToMarkdown($requirement->description),
                    'blocked_reason' => $requirement->blocked_reason,
                    'activity_at' => $requirement->activity_at,
                    'completed_at' => $requirement->completed_at,
                    'is_complete' => $requirement->is_complete,
                    'is_draft' => $requirement->is_draft,
                    'reference' => $requirement->reference,
                    'source' => $requirement->source,
                    'assignments' => $requirement->assignments->map(fn($assignment) => [
                        'id' => $assignment->sqid,
                        'actor_id' => $assignment->actor_sqid,
                    ]),
                    'tasks' => $requirement->tasks->map(fn($task) => [
                        'id' => $task->sqid,
                        'name' => $task->name,
                        'is_complete' => $task->is_complete,
                    ]),
                    'unknowns' => $requirement->unknowns->map(fn($unknown) => [
                        'id' => $unknown->sqid,
                        'name' => $unknown->name,
                    ]),
                ]),
            ]),
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
            'generated_at' => now()->toISOString(),
        ]);
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
                ->description('The specification ID returned by ListProjectsTool.')
                ->required(),
        ];
    }
}

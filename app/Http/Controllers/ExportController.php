<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Response;

class ExportController
{
    public function show(Project $project, string $type)
    {
        $project->load([
            'actors',
            'features',
            'features.requirements',
            'features.requirements.actors',
            'features.requirements.assignments.actor',
            'features.requirements.tasks',
            'features.requirements.unknowns',
        ]);

        return match ($type) {
            'html' => $this->generateHtml($project),
            'markdown' => $this->generateMarkdown($project),
            'json' => $this->generateJson($project),
            default => abort(404),
        };
    }

    protected function generateHtml(Project $project): Response
    {
        $html = view('export.html', compact('project'))->render();

        if (extension_loaded('tidy') && $html !== '') {
            $options = [
                'indent' => true,
                'indent-spaces' => 4,
                'wrap' => 0,
                'drop-empty-elements' => false,
                'show-body-only' => false,
            ];

            $html = tidy_repair_string($html, $options, 'utf8');
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    protected function generateMarkdown(Project $project): Response
    {
        $markdown = view('export.markdown', compact('project'))->render();

        return response($markdown)->header('Content-Type', 'text/markdown');
    }

    protected function generateJson(Project $project): Response
    {
        $json = json_encode($this->toExportArray($project), JSON_THROW_ON_ERROR);

        return response($json)->header('Content-Type', 'application/json');
    }

    protected function toExportArray(Project $project): array
    {
        // TODO Provide tombstones for deleted entities.

        return [
            'id' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
            'locale' => $project->locale,
            'actors' => $project->actors
                ->sortBy('created_at')
                ->sortBy('weight')
                ->values()
                ->map(fn($actor) => [
                    'id' => $actor->uuid,
                    'name' => $actor->name,
                    'summary' => $actor->summary,
                    'weight' => $actor->weight,
                ])
                ->all(),
            'features' => $project->features
                ->sortBy('created_at')
                ->sortBy('weight')
                ->values()
                ->map(fn($feature) => [
                    'id' => $feature->uuid,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'weight' => $feature->weight,
                    'requirements' => $feature->requirements
                        ->sortBy('created_at')
                        ->sortBy('weight')
                        ->values()
                        ->map(fn($requirement) => [
                            'id' => $requirement->uuid,
                            'name' => $requirement->name,
                            'description' => $requirement->description,
                            'blocked_reason' => $requirement->blocked_reason,
                            'activity_at' => $requirement->activity_at,
                            'completed_at' => $requirement->completed_at,
                            'is_draft' => $requirement->is_draft,
                            'source' => $requirement->source,
                            'reference' => $requirement->reference,
                            'weight' => $requirement->weight,
                            'tasks' => $requirement->tasks
                                ->sortBy('created_at')
                                ->sortBy('weight')
                                ->values()
                                ->map(fn($task) => [
                                    'id' => $task->uuid,
                                    'name' => $task->name,
                                    'is_complete' => (bool) $task->is_complete,
                                    'weight' => $task->weight,
                                ])
                                ->all(),
                            'unknowns' => $requirement->unknowns
                                ->sortBy('created_at')
                                ->values()
                                ->map(fn($unknown) => [
                                    'id' => $unknown->uuid,
                                    'name' => $unknown->name,
                                ])
                                ->all(),
                            'assignments' => $requirement->assignments->map(fn($assignment) => [
                                'id' => $assignment->uuid,
                                'actor_id' => $assignment->actor->uuid,
                            ])
                            ->all(),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}

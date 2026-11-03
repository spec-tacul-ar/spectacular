<?php

namespace App\Actions\Projects;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Rules\SluggableName as SluggableNameRule;
use App\Rules\SupportedLocale as SupportedLocaleRule;

class EditProject
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('project'));
    }

    public static function routes(Router $router): void
    {
        $router->post('projects/{project}/edit', static::class);
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'locale' => ['sometimes', 'string', new SupportedLocaleRule()],
            'name' => ['sometimes', 'required', 'string', 'max:250', new SluggableNameRule()],
        ];
    }

    public function handle(Project $project, array $validated): Project
    {
        $project->update($validated);

        return $project;
    }

    public function asController(ActionRequest $request, Project $project): ProjectResource
    {
        $project = $this->handle($project, $request->validated());

        return new ProjectResource($project);
    }
}

<?php

namespace App\Actions\Projects;

use App\Enums\Role;
use App\Http\Resources\ProjectResource;
use App\Models\Account;
use App\Models\Project;
use App\Rules\SluggableName as SluggableNameRule;
use App\Rules\SupportedLocale as SupportedLocaleRule;
use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateProject
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('create', Project::class);
    }

    public static function routes(Router $router): void
    {
        $router->post('projects', static::class);
    }

    public function rules(): array
    {
        return [
            'actors' => ['array', 'max:25'],
            'actors.*' => ['string', 'max:250'],
            'features' => ['array', 'max:25'],
            'features.*' => ['string', 'max:250'],
            'locale' => ['sometimes', 'string', new SupportedLocaleRule()],
            'name' => ['required', 'string', 'max:250', new SluggableNameRule()],
        ];
    }

    public function handle(Account $account, array $validated): Project
    {
        $project = Project::create($validated);

        if (array_key_exists('actors', $validated)) {
            $actors = collect($validated['actors'])
                ->values()
                ->map(fn(string $name, int $weight) => ['name' => $name, 'weight' => $weight])
                ->all();

            $project->actors()->createMany($actors);
        }

        if (array_key_exists('features', $validated)) {
            $features = collect($validated['features'])
                ->values()
                ->map(fn(string $name, int $weight) => ['name' => $name, 'weight' => $weight])
                ->all();

            $project->features()->createMany($features);
        }

        $project->addCollaboration($account, Role::OWNER);

        return $project;
    }

    public function asController(ActionRequest $request): ProjectResource
    {
        $project = $this->handle($request->user(), $request->validated());

        return new ProjectResource($project);
    }
}

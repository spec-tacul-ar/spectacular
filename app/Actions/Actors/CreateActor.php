<?php

namespace App\Actions\Actors;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\ActorResource;
use App\Models\Project;
use App\Models\Actor;
use App\Rules\Authorised;

class CreateActor
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('create', Actor::class);
    }

    public static function routes(Router $router): void
    {
        $router->post('actors', static::class)
            ->middleware('sqids:project_id');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:250'],
            'project_id' => ['required', 'integer', new Authorised('update', Project::class)],
            'summary' => ['nullable', 'string', 'max:2500'],
        ];
    }

    public function handle(array $validated): Actor
    {
        $project = Project::withMax('actors', 'weight')->findOrFail($validated['project_id']);

        $weight = $project->actors_max_weight === null ? 0 : $project->actors_max_weight + 1;

        $validated['weight'] = min($weight, 250);

        return Actor::create($validated);
    }

    public function asController(ActionRequest $request): ActorResource
    {
        $actor = $this->handle($request->validated());

        return new ActorResource($actor);
    }
}

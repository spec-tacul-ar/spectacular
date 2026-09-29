<?php

namespace App\Actions\Requirements;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\RequirementResource;
use App\Models\Actor;
use App\Models\Feature;
use App\Models\Requirement;
use App\Rules\Authorised;
use App\Rules\SharesRelation;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateRequirement
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('create', Requirement::class);
    }

    public static function routes(Router $router): void
    {
        $router->post('requirements', static::class)
            ->middleware('sqids:feature_id,actor_ids.*');
    }

    public function rules(): array
    {
        return [
            'actor_ids' => ['array'],
            'actor_ids.*' => ['integer', new SharesRelation(Actor::class, 'feature_id', 'project.features'), new Authorised('update', Actor::class)],
            'blocked_reason' => ['nullable', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:10000'],
            'feature_id' => ['required', 'integer', new Authorised('update', Feature::class)],
            'name' => ['required', 'string', 'max:250'],
            'source' => ['nullable', 'string', 'max:250'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.is_complete' => ['nullable', 'boolean'],
            'tasks.*.name' => ['required', 'string', 'max:250'],
            'unknowns' => ['nullable', 'array'],
            'unknowns.*.name' => ['required', 'string', 'max:250'],
        ];
    }

    public function handle(array $validated): Requirement
    {
        $feature = Feature::withMax('requirements', 'weight')->findOrFail($validated['feature_id']);

        $weight = $feature->requirements_max_weight === null ? 0 : $feature->requirements_max_weight + 1;

        $validated['weight'] = min($weight, 250);

        $requirement = Requirement::create($validated);

        if (!empty($validated['actor_ids'])) {
            $requirement->assignments()->createMany(array_map(fn($actorId) => ['actor_id' => $actorId], $validated['actor_ids']));
        }

        if (!empty($validated['unknowns'])) {
            $requirement->unknowns()->createMany($validated['unknowns']);
        }

        if (!empty($validated['tasks'])) {
            $tasks = collect($validated['tasks'])
                ->values()
                ->map(fn(array $task, int $index) => [...$task, 'weight' => $index])
                ->all();

            $requirement->tasks()->createMany($tasks);
        }

        // We have to fetch a new copy because the reference is set after create.
        return $requirement->fresh(['assignments', 'unknowns', 'tasks']);
    }

    public function asController(ActionRequest $request): RequirementResource
    {
        $requirement = $this->handle($request->validated());

        return new RequirementResource($requirement);
    }
}

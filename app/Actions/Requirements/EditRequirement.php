<?php

namespace App\Actions\Requirements;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\RequirementResource;
use App\Models\Feature;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\Unknown;
use App\Models\Actor;
use App\Rules\Authorised;
use App\Rules\SharesRelation;

class EditRequirement
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('requirement'));
    }

    public static function routes(Router $router): void
    {
        $router->post('requirements/{requirement}/edit', static::class)
            ->middleware('sqids:feature_id,actor_ids.*,unknowns.*.id,tasks.*.id');
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(['requirement_id' => $request->route('requirement')->id]);

        if (!$request->has('feature_id')) {
            $request->merge(['feature_id' => $request->route('requirement')->feature_id]);
        }
    }

    public function rules(): array
    {
        return [
            'actor_ids' => ['present', 'array'],
            'actor_ids.*' => ['integer', new SharesRelation(Actor::class, 'feature_id', 'project.features'), new Authorised('update', Actor::class)],
            'blocked_reason' => ['present', 'nullable', 'string', 'max:250'],
            'description' => ['present', 'nullable', 'string', 'max:10000'],
            'feature_id' => ['bail', 'required', 'integer', new Authorised('update', Feature::class)],
            'is_draft' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:250'],
            'source' => ['present', 'nullable', 'string', 'max:250'],
            'tasks' => ['present', 'array'],
            'tasks.*.id' => [
                'sometimes',
                'bail',
                'required',
                'integer',
                new Authorised('update', Task::class),
                new SharesRelation(Task::class, 'requirement_id', 'requirement'),
            ],
            'tasks.*.is_complete' => ['nullable', 'boolean'],
            'tasks.*.name' => ['required', 'string', 'max:250'],
            'tasks.*.weight' => ['nullable', 'integer', 'min:0', 'max:250'],
            'unknowns' => ['present', 'array'],
            'unknowns.*.id' => [
                'sometimes',
                'bail',
                'required',
                'integer',
                new Authorised('update', Unknown::class),
                new SharesRelation(Unknown::class, 'requirement_id', 'requirement'),
            ],
            'unknowns.*.name' => ['required', 'string', 'max:250'],
            'weight' => ['nullable', 'integer', 'between:0,250'],
        ];
    }

    public function handle(Requirement $requirement, array $validated): Requirement
    {
        if (!empty($validated['is_draft']) && !$requirement->is_draft && $requirement->is_complete) {
            throw ValidationException::withMessages([
                'is_draft' => 'Completed requirements cannot be marked as draft.',
            ]);
        }

        $requirement->update($validated);

        if (array_key_exists('actor_ids', $validated)) {
            $requirement->assignments->whereNotIn('actor_id', $validated['actor_ids'])->each->delete();

            foreach ($validated['actor_ids'] as $actorId) {
                $requirement->assignments()->updateOrCreate(['actor_id' => $actorId]);
            }

            $requirement->load('assignments');
        }

        if (array_key_exists('unknowns', $validated)) {
            $remainingKeys = array_column($validated['unknowns'], 'id');

            $requirement->unknowns->except($remainingKeys)->each->delete();

            foreach ($validated['unknowns'] as $unknown) {
                $requirement->unknowns()->updateOrCreate(
                    ['id' => $unknown['id'] ?? null],
                    $unknown,
                );
            }

            $requirement->load('unknowns');
        }

        if (array_key_exists('tasks', $validated)) {
            $remainingKeys = array_column($validated['tasks'], 'id');

            if ($remainingKeys) {
                $requirement->tasks->except($remainingKeys)->each->delete();
            }

            foreach ($validated['tasks'] as $task) {
                $requirement->tasks()->updateOrCreate(
                    ['id' => $task['id'] ?? null],
                    $task,
                );
            }

            $requirement->load('tasks');
        }

        return $requirement;
    }

    public function asController(ActionRequest $request, Requirement $requirement): RequirementResource
    {
        $requirement = $this->handle($requirement, $request->validated());

        return new RequirementResource($requirement);
    }
}

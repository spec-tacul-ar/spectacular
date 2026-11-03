<?php

namespace App\Actions\Tasks;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\TaskResource;
use App\Models\Task;

class EditTask
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('task'));
    }

    public static function routes(Router $router): void
    {
        $router->post('tasks/{task}/edit', static::class);
    }

    public function rules(): array
    {
        return [
            'is_complete' => ['sometimes', 'required', 'boolean'],
            'name' => ['sometimes', 'required', 'string', 'max:250'],
            'weight' => ['sometimes', 'nullable', 'integer', 'between:0,250'],
        ];
    }

    public function handle(Task $task, array $validated): Task
    {
        $task->update($validated);

        return $task;
    }

    public function asController(ActionRequest $request, Task $task): TaskResource
    {
        $task = $this->handle($task, $request->validated());

        return new TaskResource($task);
    }
}

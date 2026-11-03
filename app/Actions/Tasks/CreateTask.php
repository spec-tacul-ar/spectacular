<?php

namespace App\Actions\Tasks;

use App\Models\Requirement;
use App\Models\Task;
use App\Rules\Authorised;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTask
{
    use AsAction;

    public function rules(): array
    {
        return [
            'requirement_id' => ['required', 'integer', new Authorised('update', Requirement::class)],
            'name' => ['required', 'string', 'max:250'],
            'is_complete' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function handle(array $validated): Task
    {
        $requirement = Requirement::withMax('tasks', 'weight')->findOrFail($validated['requirement_id']);
        $validated['weight'] = min(250, ($requirement->tasks_max_weight ?? -1) + 1);

        return Task::create($validated);
    }
}

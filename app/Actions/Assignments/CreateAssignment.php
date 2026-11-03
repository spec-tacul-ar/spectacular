<?php

namespace App\Actions\Assignments;

use App\Models\Actor;
use App\Models\Assignment;
use App\Models\Requirement;
use App\Rules\Authorised;
use App\Rules\SharesRelation;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateAssignment
{
    use AsAction;

    public function rules(): array
    {
        return [
            'requirement_id' => ['required', 'integer', new Authorised('update', Requirement::class)],
            'actor_id' => ['required', 'integer', new Authorised('update', Actor::class), new SharesRelation(Actor::class, 'requirement_id', 'project.requirements')],
        ];
    }

    public function handle(array $validated): Assignment
    {
        $requirement = Requirement::findOrFail($validated['requirement_id']);

        return $requirement->assignments()->firstOrCreate(['actor_id' => $validated['actor_id']]);
    }
}

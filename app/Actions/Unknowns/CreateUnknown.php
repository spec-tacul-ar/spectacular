<?php

namespace App\Actions\Unknowns;

use App\Models\Requirement;
use App\Models\Unknown;
use App\Rules\Authorised;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateUnknown
{
    use AsAction;

    public function rules(): array
    {
        return [
            'requirement_id' => ['required', 'integer', new Authorised('update', Requirement::class)],
            'name' => ['required', 'string', 'max:250'],
        ];
    }

    public function handle(array $validated): Unknown
    {
        return Unknown::create($validated);
    }
}

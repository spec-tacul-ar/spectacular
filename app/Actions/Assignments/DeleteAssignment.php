<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteAssignment
{
    use AsAction;

    public function handle(Assignment $assignment): void
    {
        $assignment->delete();
    }
}

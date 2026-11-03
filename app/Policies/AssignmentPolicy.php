<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Assignment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class AssignmentPolicy
{
    use HandlesAuthorization;

    public function view(Account $account, Assignment $assignment): Response
    {
        return $account->canView($assignment, 'features.requirements.assignments')
            ? Response::allow()
            : Response::denyAsNotFound();
    }
    public function delete(Account $account, Assignment $assignment): Response
    {
        return Gate::forUser($account)->inspect('update', $assignment->requirement);
    }

}

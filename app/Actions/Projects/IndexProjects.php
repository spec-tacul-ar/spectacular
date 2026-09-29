<?php

namespace App\Actions\Projects;

use App\Http\Resources\ProjectResource;
use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexProjects
{
    use AsAction;

    public static function routes(Router $router): void
    {
        $router->get('projects', static::class);
    }

    public function handle(Account $account): LengthAwarePaginator
    {
        return $account->projects()
            ->withCount([
                'collaborations',
                'requirements',
                'requirements as blocked_requirements_count' => fn($query) => $query->whereNotNull('blocked_reason'),
                'unknowns',
                'tasks',
                'requirements as non_draft_requirements_count' => fn($query) => $query->draft(false),
                'requirements as completed_requirements_count' => fn($query) => $query->draft(false)->completed(),
            ])
            ->with(['collaborations' => fn($query) => $query->whereBelongsTo($account)])
            ->orderBy('name', 'asc')
            ->paginate(100)
            ->withQueryString();
    }

    public function asController(Request $request): ResourceCollection
    {
        $projects = $this->handle($request->user());

        return ProjectResource::collection($projects);
    }
}

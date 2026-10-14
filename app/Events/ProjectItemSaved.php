<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectItemSaved implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    protected string $project_id;

    protected string $repository;

    protected string $item_id;

    protected array $payload;

    public function __construct(Model $model)
    {
        $project = $model instanceof Project ? $model : $model->project;

        $this->project_id = $project->sqid;

        $this->repository = (string) str($model::class)->classBasename()->plural()->lower();

        $this->item_id = $model->sqid;

        $this->payload = [
            'repository' => $this->repository,
            'data' => $model->withoutRelations()->toResource()->resolve(), // Related items have their own events. Exclude loaded relationships so a stale parent update cannot reinsert a deleted child.
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('projects.' . $this->project_id),
            new PresenceChannel($this->repository . '.editing.' . $this->item_id),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

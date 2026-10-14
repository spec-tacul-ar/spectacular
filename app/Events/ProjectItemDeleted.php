<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectItemDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    protected string $project_id;

    protected array $payload;

    public function __construct(Model $model)
    {
        $project = $model instanceof Project ? $model : $model->project;

        $this->project_id = $project->sqid;

        $this->payload = [
            'repository' => (string) str($model::class)->classBasename()->plural()->lower(),
            'id' => $model->sqid,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('projects.' . $this->project_id)];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

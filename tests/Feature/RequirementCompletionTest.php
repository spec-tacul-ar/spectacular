<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_blocked_requirement_initializes_activity_without_completing_it(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $requirement = Requirement::factory()
            ->for(Feature::factory()->for(Project::factory()))
            ->create(['blocked_reason' => 'Waiting on access'])
            ->fresh();

        $this->assertNotNull($requirement->activity_at);
        $this->assertTrue($requirement->activity_at->equalTo(now()));
        $this->assertNull($requirement->completed_at);
        $this->assertTrue($requirement->is_blocked);
        $this->assertFalse($requirement->is_complete);

        $this->travelBack();
    }

    public function test_completion_is_based_on_completed_at_being_after_activity_at(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $requirement = Requirement::factory()
            ->for(Feature::factory()->for(Project::factory()))
            ->create(['blocked_reason' => null]);
        $task = Task::factory()->for($requirement)->create(['is_complete' => true]);

        $this->assertFalse($requirement->fresh()->is_complete);

        $requirement->forceFill([
            'completed_at' => $requirement->fresh()->activity_at,
        ])->saveQuietly();

        $this->assertFalse($requirement->fresh()->is_complete);

        $this->travelTo('2026-08-18 10:01:00');
        $requirement->fresh()->complete();

        $completed = $requirement->fresh();

        $this->assertTrue($completed->is_complete);
        $this->assertTrue($completed->completed_at->isAfter($completed->activity_at));

        $this->travelTo('2026-08-18 10:02:00');
        $task->update(['is_complete' => false]);

        $this->assertFalse($requirement->fresh()->is_complete);

        $this->travelBack();
    }

    public function test_reopening_a_requirement_clears_completed_at(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $requirement = Requirement::factory()
            ->for(Feature::factory()->for(Project::factory()))
            ->create(['blocked_reason' => null]);

        $this->travelTo('2026-08-18 10:01:00');
        $requirement->complete();
        $activityAt = $requirement->fresh()->activity_at;
        $requirement->reopen();

        $this->assertNull($requirement->fresh()->completed_at);
        $this->assertFalse($requirement->fresh()->is_complete);
        $this->assertTrue($requirement->fresh()->activity_at->equalTo($activityAt));

        $this->travelBack();
    }

    public function test_blocking_a_requirement_clears_completed_at(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $requirement = Requirement::factory()
            ->for(Feature::factory()->for(Project::factory()))
            ->create(['blocked_reason' => null]);

        $this->travelTo('2026-08-18 10:01:00');
        $requirement->complete();
        $requirement->update(['blocked_reason' => 'Waiting on access']);

        $this->assertNull($requirement->fresh()->completed_at);

        $this->travelBack();
    }

}

<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\Unknown;
use App\Models\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class NestedResourcesTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_requirements_read_endpoint_requires_project_view_access(): void
    {
        $project = Project::factory()->create();
        $requirement = Requirement::factory()
            ->for(Feature::factory()->for($project))
            ->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($viewer, $project, Role::VIEWER);
        $this->actingAsAccount($viewer);

        $this->getJson('/api/requirements/' . $requirement->sqid)
            ->assertOk()
            ->assertJsonPath('data.id', $requirement->sqid)
            ->assertJsonPath('data.activity_at', $requirement->activity_at->toJSON())
            ->assertJsonPath('data.completed_at', null);

        $this->actingAsAccount(Account::factory()->create());

        $this->getJson('/api/requirements/' . $requirement->sqid)->assertNotFound();
    }

    public function test_features_endpoints_require_project_edit_access(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create(['name' => 'Initial', 'weight' => 5]);
        $requirement = Requirement::factory()->for($feature)->create();

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $featureComment = Comment::factory()
            ->for($editor, 'account')
            ->for($project)
            ->create([
                'commentable_id' => $feature->id,
                'commentable_type' => 'feature',
            ]);
        $requirementComment = Comment::factory()
            ->for($editor, 'account')
            ->for($project)
            ->create([
                'commentable_id' => $requirement->id,
                'commentable_type' => 'requirement',
            ]);

        $this->actingAsAccount($editor);
        $this->postJson('/api/features', [
            'description' => 'Feature details',
            'name' => 'Workflow',
            'project_id' => $project->sqid,
            'weight' => 0,
        ])->assertCreated();

        $createdFeature = Feature::query()->where('name', 'Workflow')->firstOrFail();
        $this->assertDatabaseHas('features', [
            'id' => $createdFeature->id,
            'project_id' => $project->id,
            'weight' => 6,
        ]);

        $this->actingAsAccount($viewer);
        $this->postJson('/api/features', [
            'name' => 'Blocked feature',
            'project_id' => $project->sqid,
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');

        $this->postJson('/api/features/' . $feature->sqid . '/edit', [
            'name' => 'Blocked edit',
        ])->assertForbidden();

        $this->postJson('/api/features/' . $feature->sqid . '/delete')->assertForbidden();

        $this->actingAsAccount($editor);
        $this->postJson('/api/features/' . $feature->sqid . '/edit', [
            'description' => 'Changed',
            'name' => 'Updated',
        ])->assertOk();

        $this->assertDatabaseHas('features', [
            'id' => $feature->id,
            'description' => 'Changed',
            'name' => 'Updated',
        ]);

        $this->postJson('/api/features/' . $feature->sqid . '/delete')->assertNoContent();
        $this->assertSoftDeleted('features', ['id' => $feature->id]);
        $this->assertDatabaseMissing('comments', ['id' => $featureComment->id]);
        $this->assertDatabaseMissing('comments', ['id' => $requirementComment->id]);
    }

    public function test_actors_endpoints_require_project_edit_access(): void
    {
        $project = Project::factory()->create();
        $actor = Actor::factory()->for($project)->create(['name' => 'Initial users', 'weight' => 5]);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($editor);

        $this->getJson('/api/actors/' . $actor->sqid . '')
            ->assertOk()
            ->assertJsonPath('data.id', $actor->sqid)
            ->assertJsonPath('data.name', 'Initial users');

        $this->postJson('/api/actors', [
            'name' => 'Operators',
            'project_id' => $project->sqid,
            'summary' => 'Platform users',
            'weight' => 0,
        ])->assertCreated();

        $createdActor = Actor::query()->where('name', 'Operators')->firstOrFail();
        $this->assertDatabaseHas('actors', [
            'id' => $createdActor->id,
            'project_id' => $project->id,
            'weight' => 6,
        ]);

        $this->actingAsAccount($viewer);

        $this->getJson('/api/actors/' . $actor->sqid . '')
            ->assertOk()
            ->assertJsonPath('data.id', $actor->sqid);

        $this->postJson('/api/actors', [
            'name' => 'Blocked users',
            'project_id' => $project->sqid,
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');

        $this->postJson('/api/actors/' . $actor->sqid . '/edit', [
            'name' => 'Blocked update',
        ])->assertForbidden();

        $this->postJson('/api/actors/' . $actor->sqid . '/delete')->assertForbidden();

        $outsider = Account::factory()->create();
        $this->actingAsAccount($outsider);

        $this->getJson('/api/actors/' . $actor->sqid . '')->assertNotFound();

        $this->actingAsAccount($editor);
        $this->postJson('/api/actors/' . $actor->sqid . '/edit', [
            'name' => 'Updated users',
            'summary' => 'Updated summary',
            'weight' => 11,
        ])->assertOk();

        $this->assertDatabaseHas('actors', [
            'id' => $actor->id,
            'name' => 'Updated users',
            'summary' => 'Updated summary',
            'weight' => 11,
        ]);

        $this->postJson('/api/actors/' . $actor->sqid . '/delete')->assertNoContent();
        $this->assertSoftDeleted('actors', ['id' => $actor->id]);
    }

    public function test_new_actors_are_added_after_existing_actors(): void
    {
        $project = Project::factory()->create();
        $first = Actor::factory()->for($project)->create(['name' => 'First', 'weight' => 5]);
        $last = Actor::factory()->for($project)->create(['name' => 'Last', 'weight' => 6]);
        $editor = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->actingAsAccount($editor);

        $this->postJson('/api/actors', [
            'name' => 'New actor',
            'project_id' => $project->sqid,
        ])->assertCreated();

        $newActor = Actor::query()->where('name', 'New actor')->firstOrFail();

        $this->assertSame(7, $newActor->weight);
        $this->assertSame(
            [$first->id, $last->id, $newActor->id],
            $project->fresh()->actors->pluck('id')->all(),
        );
    }

    public function test_new_features_are_added_after_existing_features(): void
    {
        $project = Project::factory()->create();
        $first = Feature::factory()->for($project)->create(['name' => 'First', 'weight' => 5]);
        $last = Feature::factory()->for($project)->create(['name' => 'Last', 'weight' => 6]);
        $editor = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->actingAsAccount($editor);

        $this->postJson('/api/features', [
            'name' => 'New feature',
            'project_id' => $project->sqid,
        ])->assertCreated();

        $newFeature = Feature::query()->where('name', 'New feature')->firstOrFail();

        $this->assertSame(7, $newFeature->weight);
        $this->assertSame(
            [$first->id, $last->id, $newFeature->id],
            $project->fresh()->features->pluck('id')->all(),
        );
    }

    public function test_requirements_add_and_edit_endpoints_require_project_edit_access_and_reject_cross_project_actors(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create(['name' => 'Initial requirement']);
        $projectActor = Actor::factory()->for($project)->create();
        $otherProjectActor = Actor::factory()->for(Project::factory())->create();
        $existingUnknown = Unknown::factory()->for($requirement)->create(['name' => 'Old unknown?']);
        $existingTask = Task::factory()->for($requirement)->create(['name' => 'Old task', 'is_complete' => false]);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($editor);
        $this->postJson('/api/requirements', [
            'feature_id' => $feature->sqid,
            'name' => 'deliver notifications',
            'tasks' => [
                ['is_complete' => false, 'name' => 'Write implementation', 'weight' => 2],
            ],
            'unknowns' => [
                ['name' => 'Which provider?'],
            ],
            'actor_ids' => [$projectActor->sqid],
            'weight' => 3,
        ])->assertOk();

        $createdRequirement = Requirement::query()->where('name', 'deliver notifications')->firstOrFail();
        $this->assertDatabaseHas('assignments', [
            'requirement_id' => $createdRequirement->id,
            'actor_id' => $projectActor->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'requirement_id' => $createdRequirement->id,
            'name' => 'Write implementation',
            'weight' => 0,
        ]);
        $this->assertDatabaseHas('unknowns', [
            'requirement_id' => $createdRequirement->id,
            'name' => 'Which provider?',
        ]);

        $this->actingAsAccount($viewer);
        $this->postJson('/api/requirements', [
            'feature_id' => $feature->sqid,
            'name' => 'blocked requirement',
            'tasks' => [],
            'unknowns' => [],
            'actor_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('feature_id');

        $this->actingAsAccount($editor);
        $this->postJson('/api/requirements', [
            'feature_id' => $feature->sqid,
            'name' => 'cross project assignment',
            'tasks' => [],
            'unknowns' => [],
            'actor_ids' => [$otherProjectActor->sqid],
        ])->assertUnprocessable()->assertJsonValidationErrors('actor_ids.0');

        $this->actingAsAccount($viewer);
        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'name' => 'Blocked update',
            'source' => null,
            'tasks' => [],
            'unknowns' => [],
        ])->assertForbidden();

        $this->actingAsAccount($editor);
        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'blocked_reason' => null,
            'description' => null,
            'name' => 'Updated requirement',
            'source' => null,
            'tasks' => [
                ['id' => $existingTask->sqid, 'is_complete' => true, 'name' => 'Updated task', 'weight' => 6],
                ['is_complete' => false, 'name' => 'Fresh task', 'weight' => 7],
            ],
            'unknowns' => [
                ['id' => $existingUnknown->sqid, 'name' => 'Updated unknown?'],
                ['name' => 'Fresh unknown?'],
            ],
            'actor_ids' => [$projectActor->sqid],
            'weight' => 10,
        ])->assertOk();

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'name' => 'Updated requirement',
            'weight' => 10,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $existingTask->id,
            'is_complete' => true,
            'name' => 'Updated task',
            'weight' => 6,
        ]);
        $this->assertDatabaseHas('unknowns', [
            'id' => $existingUnknown->id,
            'name' => 'Updated unknown?',
        ]);
        $this->assertDatabaseHas('assignments', [
            'requirement_id' => $requirement->id,
            'actor_id' => $projectActor->id,
        ]);
    }

    public function test_new_requirements_are_added_after_existing_requirements(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $first = Requirement::factory()->for($feature)->create(['name' => 'First', 'weight' => 5]);
        $last = Requirement::factory()->for($feature)->create(['name' => 'Last', 'weight' => 6]);
        $editor = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->actingAsAccount($editor);

        $this->postJson('/api/requirements', [
            'feature_id' => $feature->sqid,
            'name' => 'New requirement',
        ])->assertOk();

        $newRequirement = Requirement::query()
            ->where('name', 'New requirement')
            ->firstOrFail();

        $this->assertSame(7, $newRequirement->weight);
        $this->assertSame(
            [$first->id, $last->id, $newRequirement->id],
            $feature->fresh()->requirements->pluck('id')->all(),
        );
    }

    public function test_requirements_complete_and_delete_endpoints_require_project_edit_access(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create([
            'blocked_reason' => 'Waiting on API access',
            'description' => 'Initial notes',
        ]);
        $taskA = Task::factory()->for($requirement)->create(['is_complete' => false]);
        $taskB = Task::factory()->for($requirement)->create(['is_complete' => false]);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $comment = Comment::factory()
            ->for($editor, 'account')
            ->for($project)
            ->create([
                'commentable_id' => $requirement->id,
                'commentable_type' => 'requirement',
            ]);

        $this->actingAsAccount($viewer);
        $this->postJson('/api/requirements/' . $requirement->sqid . '/append', [
            'text' => 'Blocked append',
        ])->assertForbidden();
        $this->postJson('/api/requirements/' . $requirement->sqid . '/block', [
            'reason' => 'Blocked block',
        ])->assertForbidden();
        $this->postJson('/api/requirements/' . $requirement->sqid . '/complete')->assertForbidden();
        $this->postJson('/api/requirements/' . $requirement->sqid . '/reopen')->assertForbidden();
        $this->postJson('/api/requirements/' . $requirement->sqid . '/delete')->assertForbidden();
        $this->postJson('/api/requirements/' . $requirement->sqid . '/unblock')->assertForbidden();

        $this->actingAsAccount($editor);
        $this->postJson('/api/requirements/' . $requirement->sqid . '/append', [
            'text' => 'Client confirmed OAuth is enough.',
        ])->assertOk();

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'description' => 'Initial notes<p>Client confirmed OAuth is enough.</p>',
        ]);

        $longRequirement = Requirement::factory()->for($feature)->create([
            'description' => str_repeat('a', 9993),
        ]);

        $this->postJson('/api/requirements/' . $longRequirement->sqid . '/append', [
            'text' => 'b',
        ])->assertUnprocessable()->assertJsonValidationErrors('text');

        $this->postJson('/api/requirements/' . $requirement->sqid . '/block', [
            'reason' => 'Waiting on API access',
        ])->assertOk();

        $this->postJson('/api/requirements/' . $requirement->sqid . '/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement');

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'blocked_reason' => 'Waiting on API access',
        ]);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/unblock')->assertOk();

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'blocked_reason' => null,
        ]);

        $activityAt = $requirement->fresh()->activity_at->toJSON();

        $completeResponse = $this->postJson('/api/requirements/' . $requirement->sqid . '/complete', [
            'activity_at' => now()->addYear()->toISOString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.activity_at', $activityAt);

        $this->assertNotNull($completeResponse->json('data.completed_at'));

        $this->assertDatabaseMissing('requirements', ['id' => $requirement->id, 'completed_at' => null]);
        $this->assertDatabaseHas('tasks', ['id' => $taskA->id, 'is_complete' => false]);
        $this->assertDatabaseHas('tasks', ['id' => $taskB->id, 'is_complete' => false]);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/reopen')
            ->assertOk()
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.activity_at', $activityAt);

        $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'completed_at' => null]);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/delete')->assertNoContent();
        $this->assertSoftDeleted('requirements', ['id' => $requirement->id]);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_tasks_edit_and_delete_endpoints_require_project_edit_access(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create();
        $task = Task::factory()->for($requirement)->create(['name' => 'Initial']);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($viewer);
        $this->postJson('/api/tasks/' . $task->sqid . '/edit', [
            'name' => 'Blocked task update',
        ])->assertForbidden();

        $this->postJson('/api/tasks/' . $task->sqid . '/toggle')->assertForbidden();
        $this->postJson('/api/tasks/' . $task->sqid . '/delete')->assertForbidden();

        $this->actingAsAccount($editor);
        $this->postJson('/api/tasks/' . $task->sqid . '/toggle')->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_complete' => true,
        ]);

        $this->postJson('/api/tasks/' . $task->sqid . '/toggle', [
            'is_complete' => false,
        ])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_complete' => false,
        ]);

        $this->postJson('/api/tasks/' . $task->sqid . '/edit', [
            'is_complete' => true,
            'name' => 'Updated task',
            'weight' => 8,
        ])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_complete' => true,
            'name' => 'Updated task',
            'weight' => 8,
        ]);

        $this->postJson('/api/tasks/' . $task->sqid . '/delete')->assertNoContent();
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_unknowns_edit_and_delete_endpoints_require_project_edit_access(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create();
        $unknown = Unknown::factory()->for($requirement)->create(['name' => 'Old?']);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($viewer);
        $this->postJson('/api/unknowns/' . $unknown->sqid . '/edit', [
            'name' => 'Blocked?',
        ])->assertForbidden();

        $this->postJson('/api/unknowns/' . $unknown->sqid . '/delete')->assertForbidden();

        $this->actingAsAccount($editor);
        $this->postJson('/api/unknowns/' . $unknown->sqid . '/edit', [
            'name' => 'New?',
        ])->assertOk();

        $this->assertDatabaseHas('unknowns', [
            'id' => $unknown->id,
            'name' => 'New?',
        ]);

        $this->postJson('/api/unknowns/' . $unknown->sqid . '/delete')->assertNoContent();
        $this->assertSoftDeleted('unknowns', ['id' => $unknown->id]);
    }
}

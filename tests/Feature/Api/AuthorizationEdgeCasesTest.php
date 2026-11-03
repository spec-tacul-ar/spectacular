<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\Unknown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class AuthorizationEdgeCasesTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_requirement_edit_ignores_a_forged_top_level_id_for_nested_validation(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $other = Requirement::factory()->for($fixture['feature'])->create();
        $task = Task::factory()->for($other)->create(['name' => 'Other task']);
        $unknown = Unknown::factory()->for($other)->create(['name' => 'Other question']);
        $this->actingAsAccount($fixture['account']);

        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/edit', [
            'id' => $other->id,
            'tasks' => [['id' => $task->sqid, 'name' => 'Hijacked task']],
            'unknowns' => [['id' => $unknown->sqid, 'name' => 'Hijacked question']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['tasks.0.id', 'unknowns.0.id']);

        $this->assertSame('Other task', $task->fresh()->name);
        $this->assertSame('Other question', $unknown->fresh()->name);
        $this->assertDatabaseHas('requirements', ['id' => $fixture['requirement']->id]);
    }

    public function test_features_cannot_be_reassigned_to_a_project_the_actor_cannot_edit(): void
    {
        $sourceProject = Project::factory()->create();
        $targetProject = Project::factory()->create();
        $feature = Feature::factory()->for($sourceProject)->create();

        $editor = Account::factory()->create();
        $this->attachCollaboration($editor, $sourceProject, Role::EDITOR);

        $this->actingAsAccount($editor);

        $this->postJson('/api/features/' . $feature->sqid . '/edit', [
            'name' => 'Moved feature',
            'project_id' => $targetProject->sqid,
        ])->assertSuccessful();

        $this->assertDatabaseHas('features', [
            'id' => $feature->id,
            'project_id' => $sourceProject->id,
            'name' => 'Moved feature',
        ]);
    }

    public function test_requirements_cannot_be_reassigned_to_a_feature_in_another_project(): void
    {
        $sourceProject = Project::factory()->create();
        $targetProject = Project::factory()->create();
        $sourceFeature = Feature::factory()->for($sourceProject)->create();
        $targetFeature = Feature::factory()->for($targetProject)->create();
        $requirement = Requirement::factory()->for($sourceFeature)->create();

        $editor = Account::factory()->create();
        $this->attachCollaboration($editor, $sourceProject, Role::EDITOR);

        $this->actingAsAccount($editor);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'feature_id' => $targetFeature->sqid,
            'name' => 'Moved requirement',
            'source' => null,
            'tasks' => [],
            'unknowns' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('feature_id');

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'feature_id' => $sourceFeature->id,
        ]);
    }

    public function test_requirement_edit_rejects_task_ids_from_another_project(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create();

        $otherProject = Project::factory()->create();
        $otherFeature = Feature::factory()->for($otherProject)->create();
        $otherRequirement = Requirement::factory()->for($otherFeature)->create();
        $foreignTask = Task::factory()->for($otherRequirement)->create(['name' => 'Foreign task']);

        $editor = Account::factory()->create();
        $this->attachCollaboration($editor, $project, Role::EDITOR);

        $this->actingAsAccount($editor);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'name' => 'Updated requirement',
            'source' => null,
            'tasks' => [
                [
                    'id' => $foreignTask->sqid,
                    'name' => 'Hijacked task',
                    'is_complete' => true,
                ],
            ],
            'unknowns' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('tasks.0.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $foreignTask->id,
            'requirement_id' => $otherRequirement->id,
            'name' => 'Foreign task',
        ]);
    }

    public function test_requirement_edit_rejects_task_ids_from_another_requirement_in_the_same_project(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create();
        $otherRequirement = Requirement::factory()->for($feature)->create();
        $otherTask = Task::factory()->for($otherRequirement)->create(['name' => 'Other task']);

        $editor = Account::factory()->create();
        $this->attachCollaboration($editor, $project, Role::EDITOR);

        $this->actingAsAccount($editor);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'name' => 'Updated requirement',
            'source' => null,
            'tasks' => [
                [
                    'id' => $otherTask->sqid,
                    'name' => 'Hijacked task',
                    'is_complete' => true,
                ],
            ],
            'unknowns' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('tasks.0.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'requirement_id' => $otherRequirement->id,
            'name' => 'Other task',
        ]);
    }

    public function test_requirement_edit_rejects_unknown_ids_from_another_project(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create();

        $otherProject = Project::factory()->create();
        $otherFeature = Feature::factory()->for($otherProject)->create();
        $otherRequirement = Requirement::factory()->for($otherFeature)->create();
        $foreignUnknown = Unknown::factory()->for($otherRequirement)->create(['name' => 'Foreign unknown?']);

        $editor = Account::factory()->create();
        $this->attachCollaboration($editor, $project, Role::EDITOR);

        $this->actingAsAccount($editor);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'name' => 'Updated requirement',
            'source' => null,
            'tasks' => [],
            'unknowns' => [
                [
                    'id' => $foreignUnknown->sqid,
                    'name' => 'Hijacked unknown?',
                ],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('unknowns.0.id');

        $this->assertDatabaseHas('unknowns', [
            'id' => $foreignUnknown->id,
            'requirement_id' => $otherRequirement->id,
            'name' => 'Foreign unknown?',
        ]);
    }

    public function test_requirement_edit_rejects_unknown_ids_from_another_requirement_in_the_same_project(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($feature)->create();
        $otherRequirement = Requirement::factory()->for($feature)->create();
        $otherUnknown = Unknown::factory()->for($otherRequirement)->create(['name' => 'Other unknown?']);

        $editor = Account::factory()->create();
        $this->attachCollaboration($editor, $project, Role::EDITOR);

        $this->actingAsAccount($editor);

        $this->postJson('/api/requirements/' . $requirement->sqid . '/edit', [
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'name' => 'Updated requirement',
            'source' => null,
            'tasks' => [],
            'unknowns' => [
                [
                    'id' => $otherUnknown->sqid,
                    'name' => 'Hijacked unknown?',
                ],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('unknowns.0.id');

        $this->assertDatabaseHas('unknowns', [
            'id' => $otherUnknown->id,
            'requirement_id' => $otherRequirement->id,
            'name' => 'Other unknown?',
        ]);
    }

    public function test_browse_projects_does_not_leak_non_member_projects(): void
    {
        $visibleProject = Project::factory()->create(['name' => 'Visible']);
        $hiddenProject = Project::factory()->create(['name' => 'Hidden']);
        $account = Account::factory()->create();

        $this->attachCollaboration($account, $visibleProject, Role::VIEWER);
        Feature::factory()->for($hiddenProject)->create();

        $this->actingAsAccount($account);

        $response = $this->getJson('/api/projects');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Visible');
    }

    public function test_soft_deleted_nested_resources_cannot_be_modified_through_api_routes(): void
    {
        $featureFixture = $this->createProjectFixture(Role::EDITOR);
        $taskFixture = $this->createProjectFixture(Role::EDITOR);

        $this->actingAsAccount($featureFixture['account']);
        $this->postJson('/api/features/' . $featureFixture['feature']->sqid . '/delete')->assertNoContent();
        $this->postJson('/api/features/' . $featureFixture['feature']->sqid . '/edit', [
            'name' => 'Should not work',
        ])->assertNotFound();

        $this->actingAsAccount($taskFixture['account']);
        $this->postJson('/api/tasks/' . $taskFixture['task']->sqid . '/delete')->assertNoContent();
        $this->postJson('/api/tasks/' . $taskFixture['task']->sqid . '/edit', [
            'name' => 'Should not work',
        ])->assertNotFound();
    }

    public function test_account_delete_cleans_up_comments_invitations_and_shared_collaborations(): void
    {
        $ownedProject = Project::factory()->create();
        $sharedProject = Project::factory()->create();

        $account = Account::factory()->create();
        $otherOwner = Account::factory()->create();
        $feature = Feature::factory()->for($sharedProject)->create();

        $this->attachCollaboration($account, $ownedProject, Role::OWNER);
        $sharedCollaboration = $this->attachCollaboration($account, $sharedProject, Role::VIEWER);
        $this->attachCollaboration($otherOwner, $sharedProject, Role::OWNER);

        $comment = Comment::factory()
            ->for($account, 'account')
            ->for($sharedProject)
            ->create([
                'commentable_id' => $feature->id,
                'commentable_type' => 'feature',
            ]);

        $invitation = Invitation::factory()
            ->for($account, 'account')
            ->for($sharedProject)
            ->create();

        $account->markAsRead($sharedProject);

        $this->actingAsAccount($account);

        $this->postJson('/api/account/delete', [
            'confirmation' => true,
        ])->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
        $this->assertDatabaseMissing('collaborations', ['id' => $sharedCollaboration->id]);
        $this->assertDatabaseHas('projects', ['id' => $sharedProject->id]);
    }
}

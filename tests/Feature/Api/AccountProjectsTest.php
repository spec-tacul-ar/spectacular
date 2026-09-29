<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\Unknown;
use App\Models\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class AccountProjectsTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_account_edit_endpoint_updates_the_authenticated_account(): void
    {
        $account = $this->actingAsAccount();

        $response = $this->postJson('/api/account/edit', [
            'name' => 'Renamed Account',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Renamed Account');
        $response->assertJsonPath('data.email', $account->email);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Renamed Account',
        ]);
    }

    public function test_account_delete_endpoint_deletes_the_account_and_owned_projects(): void
    {
        $account = Account::factory()->create();

        $ownedProject = Project::factory()->create();
        $this->attachCollaboration($account, $ownedProject, Role::OWNER);

        $sharedOwner = Account::factory()->create();
        $sharedProject = Project::factory()->create();
        $this->attachCollaboration($sharedOwner, $sharedProject, Role::OWNER);
        $this->attachCollaboration($account, $sharedProject, Role::VIEWER);

        $this->actingAsAccount($account);

        $response = $this->postJson('/api/account/delete', [
            'confirmation' => true,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertSoftDeleted('projects', ['id' => $ownedProject->id]);
        $this->assertDatabaseHas('projects', ['id' => $sharedProject->id]);
        $this->assertDatabaseMissing('collaborations', [
            'account_id' => $account->id,
            'project_id' => $sharedProject->id,
        ]);
    }

    public function test_projects_browse_endpoint_returns_only_visible_projects_with_aggregate_counts(): void
    {
        $account = Account::factory()->create();

        $alpha = Project::factory()->create(['name' => 'Alpha']);
        $beta = Project::factory()->create(['name' => 'Beta']);
        $hidden = Project::factory()->create(['name' => 'Hidden']);

        $this->attachCollaboration($account, $alpha, Role::OWNER);
        $this->attachCollaboration($account, $beta, Role::VIEWER);

        $account->markAsRead($alpha, now()->subMinutes(5));
        $readmark = $account->collaborations()->whereBelongsTo($alpha)->firstOrFail();

        $otherAccount = Account::factory()->create();
        $this->attachCollaboration($otherAccount, $alpha, Role::VIEWER);
        $otherAccount->markAsRead($alpha, now()->subMinute());

        $feature = Feature::factory()->for($alpha)->create();
        $blockedRequirement = Requirement::factory()->for($feature)->create(['blocked_reason' => 'Waiting on vendor']);
        $openRequirement = Requirement::factory()->for($feature)->create(['blocked_reason' => null]);
        $staleRequirement = Requirement::factory()->for($feature)->create(['blocked_reason' => null]);

        Task::factory()->for($blockedRequirement)->create(['is_complete' => true]);
        Task::factory()->for($openRequirement)->create(['is_complete' => false]);
        Task::factory()->for($staleRequirement)->create(['is_complete' => true]);
        Unknown::factory()->for($blockedRequirement)->create();

        $openRequirement->forceFill([
            'activity_at' => now()->subMinute(),
            'completed_at' => now(),
        ])->saveQuietly();
        $staleRequirement->forceFill([
            'activity_at' => now(),
            'completed_at' => now()->subMinute(),
        ])->saveQuietly();

        Feature::factory()->for($hidden)->create();

        $this->actingAsAccount($account);

        $response = $this->getJson('/api/projects');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Alpha');
        $response->assertJsonPath('data.0.requirements_count', 3);
        $response->assertJsonPath('data.0.blocked_requirements_count', 1);
        $response->assertJsonPath('data.0.unknowns_count', 1);
        $response->assertJsonPath('data.0.tasks_count', 3);
        $response->assertJsonPath('data.0.completed_requirements_count', 1);
        $response->assertJsonMissingPath('data.0.requirements_with_tasks_count');
        $response->assertJsonMissingPath('data.0.requirements_all_tasks_complete_count');
        $response->assertJsonPath('data.0.read_at', $readmark->read_at->toJSON());
        $response->assertJsonPath('data.0.collaborations.0.account_id', $account->sqid);
        $response->assertJsonMissingPath('data.0.collaborations.0.account_name');
        $response->assertJsonPath('data.1.name', 'Beta');
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_projects_browse_endpoint_does_not_include_projects_without_collaborations(): void
    {
        $account = Account::factory()->create();

        $owned = Project::factory()->create(['name' => 'Owned']);
        $unshared = Project::factory()->create(['name' => 'Unshared']);

        $this->attachCollaboration($account, $owned, Role::OWNER);

        $this->actingAsAccount($account);

        $response = $this->getJson('/api/projects');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.name', 'Owned');
        $response->assertJsonMissing(['name' => $unshared->name]);
    }

    public function test_projects_add_endpoint_creates_a_project_and_attaches_the_authenticated_account_as_owner(): void
    {
        $account = $this->actingAsAccount();

        $response = $this->postJson('/api/projects', [
            'features' => ['Authentication', 'Invoicing'],
            'locale' => 'fr',
            'name' => 'Roadmap',
            'actors' => ['Operators', 'Administrators'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Roadmap');
        $response->assertJsonPath('data.locale', 'fr');

        $project = Project::query()->where('name', 'Roadmap')->firstOrFail();

        $this->assertDatabaseHas('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
            'role' => Role::OWNER->value,
        ]);
        $this->assertDatabaseHas('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
        ]);
        $this->assertDatabaseMissing('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
            'created_at' => null,
        ]);
        $this->assertDatabaseMissing('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
            'updated_at' => null,
        ]);
        $this->assertDatabaseHas('actors', [
            'name' => 'Operators',
            'project_id' => $project->id,
            'weight' => 0,
        ]);
        $this->assertDatabaseHas('actors', [
            'name' => 'Administrators',
            'project_id' => $project->id,
            'weight' => 1,
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'Authentication',
            'project_id' => $project->id,
            'weight' => 0,
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'Invoicing',
            'project_id' => $project->id,
            'weight' => 1,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'locale' => 'fr',
        ]);
    }

    public function test_projects_add_endpoint_rejects_an_unsupported_locale(): void
    {
        $this->actingAsAccount();

        $this->postJson('/api/projects', [
            'locale' => 'ja',
            'name' => 'Roadmap',
        ])->assertUnprocessable()->assertJsonValidationErrors('locale');
    }

    public function test_projects_demo_endpoint_sets_collaboration_timestamps_for_real_accounts(): void
    {
        $account = $this->actingAsAccount();

        $response = $this->postJson('/api/projects/demo');

        $response->assertCreated();

        $project = (new Project())->resolveRouteBinding($response->json('data.id'));

        $this->assertDatabaseHas('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
            'role' => Role::OWNER->value,
        ]);
        $this->assertDatabaseMissing('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
            'created_at' => null,
        ]);
        $this->assertDatabaseMissing('collaborations', [
            'account_id' => $account->id,
            'project_id' => $project->id,
            'updated_at' => null,
        ]);
    }

    public function test_projects_read_endpoint_returns_hydrated_data_to_collaborations_and_forbids_outsiders(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);
        $fixture['account']->markAsRead($fixture['project'], now()->subMinute());

        $this->actingAsAccount($fixture['account']);

        $response = $this->getJson('/api/projects/' . $fixture['project']->sqid . '?hydrated=1');

        $response->assertOk();
        $response->assertJsonPath('data.id', $fixture['project']->sqid);
        $response->assertJsonPath('data.actors.0.id', $fixture['projectActor']->sqid);
        $response->assertJsonPath('data.features.0.id', $fixture['feature']->sqid);
        $response->assertJsonPath('data.features.0.requirements.0.id', $fixture['requirement']->sqid);
        $response->assertJsonPath('data.features.0.requirements.0.assignments.0.actor_id', $fixture['projectActor']->sqid);
        $response->assertJsonPath('data.features.0.requirements.0.tasks.0.id', $fixture['task']->sqid);
        $response->assertJsonPath('data.features.0.requirements.0.unknowns.0.id', $fixture['unknown']->sqid);
        $response->assertJsonMissingPath('data.collaborations.0.account_name');
        $response->assertJsonPath('data.read_at', $fixture['collaboration']->fresh()->read_at->toJSON());

        $outsider = Account::factory()->create();
        $this->actingAsAccount($outsider);

        $this->getJson('/api/projects/' . $fixture['project']->sqid . '')->assertNotFound();
    }

    public function test_projects_read_endpoint_returns_not_found_for_malformed_ids(): void
    {
        $this->actingAsAccount();

        $this->getJson('/api/projects/not-an-id')->assertNotFound();
    }

    public function test_projects_edit_endpoint_allows_editors_and_forbids_viewers(): void
    {
        $project = Project::factory()->create(['name' => 'Before']);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($editor);

        $this->postJson('/api/projects/' . $project->sqid . '/edit', [
            'description' => 'Updated description',
            'locale' => 'fr',
            'name' => 'After',
        ])->assertOk()->assertJsonPath('data.locale', 'fr');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'description' => 'Updated description',
            'locale' => 'fr',
            'name' => 'After',
        ]);

        $this->actingAsAccount($viewer);

        $this->postJson('/api/projects/' . $project->sqid . '/edit', [
            'name' => 'Blocked',
        ])->assertForbidden();
    }

    public function test_projects_edit_endpoint_rejects_an_unsupported_locale(): void
    {
        $project = Project::factory()->create();
        $owner = Account::factory()->create();

        $this->attachCollaboration($owner, $project, Role::OWNER);
        $this->actingAsAccount($owner);

        $this->postJson('/api/projects/' . $project->sqid . '/edit', [
            'locale' => 'ja',
            'name' => $project->name,
        ])->assertUnprocessable()->assertJsonValidationErrors('locale');
    }

    public function test_projects_organise_endpoint_allows_editors_and_forbids_viewers(): void
    {
        $project = Project::factory()->create();
        $actor = Actor::factory()->for($project)->create(['weight' => 1]);
        $feature = Feature::factory()->for($project)->create(['weight' => 1]);
        $requirement = Requirement::factory()->for($feature)->create(['weight' => 1]);

        $editor = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($editor);

        $this->postJson('/api/projects/' . $project->sqid . '/organise', [
            'features' => [
                ['id' => $feature->sqid, 'weight' => 22],
            ],
            'requirements' => [
                ['id' => $requirement->sqid, 'feature_id' => $feature->sqid, 'weight' => 33],
            ],
            'actors' => [
                ['id' => $actor->sqid, 'weight' => 11],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('actors', ['id' => $actor->id, 'weight' => 11]);
        $this->assertDatabaseHas('features', ['id' => $feature->id, 'weight' => 22]);
        $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'weight' => 33]);

        $this->actingAsAccount($viewer);

        $this->postJson('/api/projects/' . $project->sqid . '/organise', [
            'features' => [],
            'requirements' => [],
            'actors' => [],
        ])->assertForbidden();
    }

    public function test_projects_organise_endpoint_accepts_partial_payloads(): void
    {
        $project = Project::factory()->create();
        $feature = Feature::factory()->for($project)->create(['weight' => 1]);
        $editor = Account::factory()->create();

        $this->attachCollaboration($editor, $project, Role::EDITOR);
        $this->actingAsAccount($editor);

        $this->postJson('/api/projects/' . $project->sqid . '/organise', [
            'features' => [
                ['id' => $feature->sqid, 'weight' => 22],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('features', [
            'id' => $feature->id,
            'weight' => 22,
        ]);
    }

    public function test_archived_projects_reject_project_and_child_updates(): void
    {
        $project = Project::factory()->archived()->create(['name' => 'Before']);
        $feature = Feature::factory()->for($project)->create(['name' => 'Feature before', 'weight' => 1]);
        $owner = Account::factory()->create();

        $this->attachCollaboration($owner, $project, Role::OWNER);
        $this->actingAsAccount($owner);

        $this->postJson('/api/projects/' . $project->sqid . '/edit', [
            'name' => 'After',
        ])->assertForbidden();

        $this->postJson('/api/projects/' . $project->sqid . '/organise', [
            'features' => [
                ['id' => $feature->sqid, 'weight' => 22],
            ],
        ])->assertForbidden();

        $this->postJson('/api/features/' . $feature->sqid . '/edit', [
            'name' => 'Feature after',
        ])->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Before',
        ]);
        $this->assertDatabaseHas('features', [
            'id' => $feature->id,
            'name' => 'Feature before',
            'weight' => 1,
        ]);
    }

    public function test_projects_delete_endpoint_requires_owner_role(): void
    {
        $project = Project::factory()->create();

        $owner = Account::factory()->create();
        $editor = Account::factory()->create();

        $this->attachCollaboration($owner, $project, Role::OWNER);
        $this->attachCollaboration($editor, $project, Role::EDITOR);

        $this->actingAsAccount($editor);
        $this->postJson('/api/projects/' . $project->sqid . '/delete')->assertForbidden();

        $this->actingAsAccount($owner);
        $this->postJson('/api/projects/' . $project->sqid . '/delete')->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_projects_delete_endpoint_hard_deletes_nested_specification_items(): void
    {
        $fixture = $this->createProjectFixture();

        $fixture['requirement']->delete();

        $this->actingAsAccount($fixture['account']);
        $this->postJson('/api/projects/' . $fixture['project']->sqid . '/delete')->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $fixture['project']->id]);
        $this->assertDatabaseMissing('features', ['id' => $fixture['feature']->id]);
        $this->assertDatabaseMissing('requirements', ['id' => $fixture['requirement']->id]);
        $this->assertDatabaseMissing('assignments', ['id' => $fixture['assignment']->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $fixture['task']->id]);
        $this->assertDatabaseMissing('unknowns', ['id' => $fixture['unknown']->id]);
        $this->assertDatabaseMissing('actors', ['id' => $fixture['projectActor']->id]);
    }

    public function test_projects_archive_endpoint_requires_owner_role(): void
    {
        $project = Project::factory()->create();

        $owner = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($owner, $project, Role::OWNER);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($owner);

        $response = $this->postJson('/api/projects/' . $project->sqid . '/archive');

        $response->assertOk();
        $response->assertJsonPath('data.id', $project->sqid);
        $this->assertNotNull($response->json('data.archived_at'));
        $this->assertNotNull($project->fresh()->archived_at);

        $project->restore();

        $this->actingAsAccount($viewer);

        $this->postJson('/api/projects/' . $project->sqid . '/archive')->assertForbidden();
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_projects_archive_endpoint_preserves_existing_archive_date(): void
    {
        $archivedAt = now()->subDay()->startOfSecond();
        $project = Project::factory()->create(['archived_at' => $archivedAt]);
        $owner = Account::factory()->create();

        $this->attachCollaboration($owner, $project, Role::OWNER);
        $this->actingAsAccount($owner);

        $this->postJson('/api/projects/' . $project->sqid . '/archive')
            ->assertOk();

        $this->assertTrue($project->fresh()->archived_at->equalTo($archivedAt));
    }

    public function test_projects_restore_endpoint_requires_owner_role(): void
    {
        $project = Project::factory()->archived()->create();

        $owner = Account::factory()->create();
        $viewer = Account::factory()->create();

        $this->attachCollaboration($owner, $project, Role::OWNER);
        $this->attachCollaboration($viewer, $project, Role::VIEWER);

        $this->actingAsAccount($owner);

        $response = $this->postJson('/api/projects/' . $project->sqid . '/restore');

        $response->assertOk();
        $response->assertJsonPath('data.id', $project->sqid);
        $response->assertJsonPath('data.archived_at', null);
        $this->assertNull($project->fresh()->archived_at);

        $project = $project->fresh();
        $project->archive();

        $this->actingAsAccount($viewer);

        $this->postJson('/api/projects/' . $project->sqid . '/restore')->assertForbidden();
        $this->assertNotNull($project->fresh()->archived_at);
    }

    public function test_projects_readmark_endpoint_marks_projects_as_read_for_collaborations_and_forbids_outsiders(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);

        $this->actingAsAccount($fixture['account']);

        $response = $this->postJson('/api/projects/' . $fixture['project']->getRouteKey() . '/readmark');

        $response->assertOk();
        $this->assertNotNull($response->json('data'));
        $this->assertDatabaseHas('collaborations', [
            'account_id' => $fixture['account']->id,
            'project_id' => $fixture['project']->id,
        ]);
        $this->assertNotNull($fixture['collaboration']->fresh()->read_at);

        $outsider = Account::factory()->create();
        $this->actingAsAccount($outsider);

        $this->postJson('/api/projects/' . $fixture['project']->getRouteKey() . '/readmark')->assertNotFound();
    }

    public function test_fetching_project_changes_uses_the_project_from_the_url(): void
    {
        $this->travelTo('2026-01-01 00:00:00');

        $fixture = $this->createProjectFixture();
        $since = now()->toISOString();

        $this->travelTo('2026-01-01 00:01:00');
        $fixture['feature']->update(['name' => 'Updated feature']);

        $this->actingAsAccount($fixture['account']);

        $this->getJson('/api/projects/' . $fixture['project']->sqid . '/changes?since=' . urlencode($since))
            ->assertOk()
            ->assertJsonPath('data.features.0.id', $fixture['feature']->sqid)
            ->assertJsonPath('data.features.0.name', 'Updated feature')
            ->assertJsonMissingPath('data.project');

        $this->travelBack();
    }

    public function test_fetching_project_changes_returns_empty_collections_when_project_is_unchanged(): void
    {
        $this->travelTo('2026-01-01 00:00:00');

        $fixture = $this->createProjectFixture();
        $since = now()->toISOString();

        $this->actingAsAccount($fixture['account']);

        $this->getJson('/api/projects/' . $fixture['project']->sqid . '/changes?since=' . urlencode($since))
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->travelBack();
    }

    public function test_fetching_project_changes_rejects_unauthorized_projects(): void
    {
        $fixture = $this->createProjectFixture();
        $otherAccount = Account::factory()->create();

        $this->actingAsAccount($otherAccount);

        $this->getJson('/api/projects/' . $fixture['project']->sqid . '/changes?since=' . urlencode(now()->toISOString()))
            ->assertNotFound();
    }

    public function test_fetching_project_changes_rejects_a_future_since_timestamp(): void
    {
        $fixture = $this->createProjectFixture();

        $this->actingAsAccount($fixture['account']);

        $this->getJson('/api/projects/' . $fixture['project']->sqid . '/changes?since=' . urlencode(now()->addSecond()->toISOString()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('since');
    }
}

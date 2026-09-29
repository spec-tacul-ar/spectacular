<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Http\Resources\RequirementResource;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class RequirementDraftTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_requirements_can_be_created_as_drafts(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);

        $this->postJson('/api/requirements', [
            'feature_id' => $fixture['feature']->sqid,
            'name' => 'plan the next phase',
            'is_draft' => true,
        ])->assertOk()->assertJsonPath('data.is_draft', true);

        $this->assertDatabaseHas('requirements', ['name' => 'plan the next phase', 'is_draft' => true]);
    }

    public function test_draft_can_be_toggled_when_editing(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $this->actingAsAccount($fixture['account']);

        foreach ([true, false] as $isDraft) {
            $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/edit', [
                ...$this->editPayload($fixture),
                'is_draft' => $isDraft,
            ])->assertOk()->assertJsonPath('data.is_draft', $isDraft);

            $this->assertSame($isDraft, $fixture['requirement']->fresh()->is_draft);
        }
    }

    public function test_create_and_edit_allow_drafts_with_completed_tasks(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);
        $payload = [
            ...$this->editPayload($fixture),
            'is_draft' => true,
            'tasks' => [['name' => 'Finished task', 'is_complete' => true]],
        ];

        $this->postJson('/api/requirements', $payload)
            ->assertOk()->assertJsonPath('data.is_draft', true)->assertJsonPath('data.completed_at', null);
        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/edit', $payload)
            ->assertOk()->assertJsonPath('data.is_draft', true)->assertJsonPath('data.completed_at', null);

        $this->assertTrue($fixture['requirement']->fresh()->is_draft);
        $this->assertFalse($fixture['requirement']->fresh()->is_complete);
        $this->assertDatabaseCount('requirements', 2);
    }

    public function test_editing_can_preserve_or_remove_an_existing_draft_after_tasks_are_completed(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);
        $fixture['requirement']->update(['is_draft' => true]);
        $fixture['task']->update(['is_complete' => true]);

        foreach ([true, false] as $isDraft) {
            $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/edit', [
                ...$this->editPayload($fixture),
                'is_draft' => $isDraft,
            ])->assertOk()->assertJsonPath('data.is_draft', $isDraft);
        }
    }

    private function editPayload(array $fixture): array
    {
        return [
            'feature_id' => $fixture['feature']->sqid,
            'name' => $fixture['requirement']->name,
            'actor_ids' => [],
            'blocked_reason' => null,
            'description' => null,
            'source' => null,
            'unknowns' => [],
            'tasks' => [[
                'id' => $fixture['task']->sqid,
                'name' => $fixture['task']->name,
                'is_complete' => $fixture['task']->is_complete,
            ]],
        ];
    }

    public function test_draft_defaults_to_a_boolean_false(): void
    {
        $fixture = $this->createProjectFixture();
        $this->assertFalse((new RequirementResource($fixture['requirement']))->resolve()['is_draft']);

        $requirement = $fixture['requirement']->fresh();

        $this->assertFalse($requirement->is_draft);
        $this->assertFalse((new RequirementResource($requirement))->resolve()['is_draft']);
    }

    public function test_editors_can_toggle_draft_while_preserving_the_blocked_reason(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $this->actingAsAccount($fixture['account']);
        $requirement = $fixture['requirement'];
        $requirement->update(['blocked_reason' => 'Waiting for details']);

        foreach ([true, false] as $isDraft) {
            $this->postJson('/api/requirements/' . $requirement->sqid . '/draft', ['is_draft' => $isDraft])
                ->assertOk()
                ->assertJsonPath('data.is_draft', $isDraft)
                ->assertJsonPath('data.blocked_reason', 'Waiting for details');

            $this->assertSame($isDraft, $requirement->fresh()->is_draft);
        }
    }

    public function test_completed_requirements_cannot_be_marked_as_draft(): void
    {
        $this->travelTo('2026-08-18 10:00:00');
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);
        $fixture['requirement']->update(['blocked_reason' => null]);
        $this->travelTo('2026-08-18 10:01:00');
        $fixture['requirement']->complete();
        $completed_at = $fixture['requirement']->completed_at->toJSON();

        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/draft', ['is_draft' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('is_draft');
        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/edit', [
            ...$this->editPayload($fixture),
            'name' => 'Rejected name',
            'is_draft' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_draft');

        $requirement = $fixture['requirement']->fresh();
        $this->assertFalse($requirement->is_draft);
        $this->assertTrue($requirement->is_complete);
        $this->assertSame($completed_at, $requirement->completed_at->toJSON());
        $this->assertSame($fixture['requirement']->name, $requirement->name);
        $this->travelBack();
    }

    public function test_api_completion_clears_draft_and_preserves_activity_and_tasks(): void
    {
        $this->travelTo('2026-08-18 10:00:00');
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $this->actingAsAccount($fixture['account']);
        $fixture['requirement']->update(['is_draft' => true, 'blocked_reason' => null]);
        $activity_at = $fixture['requirement']->activity_at->toJSON();

        $this->travelTo('2026-08-18 10:01:00');
        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/complete')
            ->assertOk()
            ->assertJsonPath('data.is_draft', false)
            ->assertJsonPath('data.activity_at', $activity_at)
            ->assertJsonPath('data.completed_at', now()->toJSON());

        $this->assertTrue($fixture['requirement']->fresh()->is_complete);
        $this->assertFalse($fixture['task']->fresh()->is_complete);
        $this->travelBack();
    }

    public function test_blocked_draft_cannot_be_completed_by_api(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);
        $fixture['requirement']->update(['is_draft' => true, 'blocked_reason' => 'Waiting on access']);
        $activity_at = $fixture['requirement']->fresh()->activity_at->toJSON();

        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/complete')
            ->assertUnprocessable()->assertJsonValidationErrors('requirement');

        $requirement = $fixture['requirement']->fresh();
        $this->assertTrue($requirement->is_draft);
        $this->assertNull($requirement->completed_at);
        $this->assertSame($activity_at, $requirement->activity_at->toJSON());
    }

    public function test_draft_can_be_removed_after_tasks_are_completed(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);
        $fixture['requirement']->update(['is_draft' => true]);
        $fixture['task']->update(['is_complete' => true]);

        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/draft', ['is_draft' => false])
            ->assertOk()->assertJsonPath('data.is_draft', false);
    }

    public function test_requirements_without_tasks_can_be_drafts(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account']);
        $fixture['task']->delete();

        $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/draft', ['is_draft' => true])
            ->assertOk()->assertJsonPath('data.is_draft', true);
    }

    public function test_draft_requires_a_boolean_and_write_access(): void
    {
        $fixture = $this->createProjectFixture();
        $endpoint = '/api/requirements/' . $fixture['requirement']->sqid . '/draft';
        $this->actingAsAccount($fixture['account']);

        foreach ([[], ['is_draft' => null], ['is_draft' => 'draft']] as $payload) {
            $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors('is_draft');
        }

        $viewer = Account::factory()->create();
        $this->attachCollaboration($viewer, $fixture['project'], Role::VIEWER);
        $this->actingAsAccount($viewer);
        $this->postJson($endpoint, ['is_draft' => true])->assertForbidden();

        $this->actingAsAccount();
        $this->postJson($endpoint, ['is_draft' => true])->assertNotFound();
        $this->actingAsAccount($fixture['account']);
        $this->assertFalse($fixture['requirement']->fresh()->is_draft);
    }
}

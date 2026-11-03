<?php

namespace Tests\Feature\Mcp;

use App\Events\ProjectItemDeleted;
use Tests\Support\DirectSpecificationsServer as SpecificationsServer;
use App\Mcp\Tools\Features\FeatureDeleteTool;
use App\Mcp\Tools\Requirements\RequirementDeleteTool;
use App\Mcp\Tools\Requirements\RequirementEditTool;
use App\Mcp\Tools\Unknowns\UnknownResolveTool;
use App\Models\Actor;
use App\Models\Requirement;
use App\Models\Unknown;
use Illuminate\Broadcasting\PendingBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class SpecificationWriteSafetyTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    #[DataProvider('deletionTypes')]
    public function test_each_delete_tool_preserves_all_prior_revisions(string $type): void
    {
        $fixture = $this->createProjectFixture();
        $item = $fixture[$type === 'actor' ? 'projectActor' : $type];
        if ($type === 'actor') {
            $fixture['assignment']->delete();
        }
        $item->update($type === 'assignment'
            ? ['actor_id' => Actor::factory()->for($fixture['project'])->create()->id]
            : ['name' => 'Edited before deletion']);
        $prior = $item->newQuery()->withHistory()->findOrFail($item->id)->history->all();
        $this->assertNotEmpty($prior);

        $entity = ucfirst($type);
        $tool = 'App\\Mcp\\Tools\\' . $entity . 's\\' . $entity . 'DeleteTool';
        SpecificationsServer::actingAs($fixture['account'])->tool($tool, ['id' => $item->sqid])->assertOk();

        $deleted = $item->newQuery()->withTrashed()->withHistory()->findOrFail($item->id);
        $this->assertTrue($deleted->trashed());
        $this->assertSame($prior, $deleted->history->take(count($prior))->all());
        $this->assertCount(count($prior) + 1, $deleted->history);
        $this->assertSame(['deleted_at' => null], $deleted->history->last()['data']);
        $this->assertSame($fixture['account']->sqid, $deleted->history->last()['account_id']);
        $this->assertTrue($deleted->history->last()['is_ai']);
    }

    public static function deletionTypes(): array
    {
        return array_map(fn($type) => [$type], ['actor', 'feature', 'requirement', 'task', 'unknown', 'assignment']);
    }

    public function test_cascading_deletion_preserves_descendant_revisions(): void
    {
        $fixture = $this->createProjectFixture();
        $prior = [];
        foreach (['feature', 'requirement', 'task', 'unknown', 'assignment'] as $key) {
            $item = $fixture[$key];
            $item->update($key === 'assignment'
                ? ['actor_id' => Actor::factory()->for($fixture['project'])->create()->id]
                : ['name' => 'Edited ' . $key]);
            $prior[$key] = $item->newQuery()->withHistory()->findOrFail($item->id)->history->all();
        }

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(FeatureDeleteTool::class, ['id' => $fixture['feature']->sqid])->assertOk();

        foreach ($prior as $key => $history) {
            $item = $fixture[$key];
            $deleted = $item->newQuery()->withTrashed()->withHistory()->findOrFail($item->id);
            $this->assertTrue($deleted->trashed());
            $this->assertSame($history, $deleted->history->take(count($history))->all());
            $this->assertCount(count($history) + 1, $deleted->history);
        }
    }

    public function test_unknown_resolution_preserves_an_edit_committed_after_initial_discovery(): void
    {
        $fixture = $this->createProjectFixture();
        $fixture['requirement']->update(['description' => '<p>Original.</p>']);
        $concurrent_description = '<p>Original.</p><p>Concurrent clarification.</p>';
        $this->interleaveDescriptionEdit($fixture, $concurrent_description);

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => 'Answer A.'])->assertOk();

        $requirement = Requirement::withHistory()->findOrFail($fixture['requirement']->id);
        $this->assertSame($concurrent_description . "<p>Answer A.</p>\n", $requirement->description);
        $this->assertSame($concurrent_description, $requirement->history->last()['data']['description']);
        $this->assertSoftDeleted($fixture['unknown']);
        $unknown = Unknown::withTrashed()->withHistory()->findOrFail($fixture['unknown']->id);
        $this->assertTrue($unknown->history->last()['is_ai']);
    }

    public function test_unknown_resolution_checks_the_latest_description_length(): void
    {
        $fixture = $this->createProjectFixture();
        $fixture['requirement']->update(['description' => '<p>Original.</p>']);
        $concurrent_description = '<p>' . str_repeat('x', 9988) . '</p>';
        $this->interleaveDescriptionEdit($fixture, $concurrent_description);

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => 'Answer A.'])->assertHasErrors();

        $this->assertSame($concurrent_description, $fixture['requirement']->fresh()->description);
        $this->assertNotSoftDeleted($fixture['unknown']);
    }

    public function test_unknown_resolution_rolls_back_description_and_history_when_deletion_fails(): void
    {
        $fixture = $this->createProjectFixture();
        $requirement = $fixture['requirement']->fresh();
        $requirement->loadHistory();
        $prior = $requirement->history->all();
        Unknown::deleting(function (Unknown $unknown) use ($fixture): void {
            if ($unknown->id === $fixture['unknown']->id) {
                throw ValidationException::withMessages(['id' => 'Deletion failed.']);
            }
        });

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => 'Answer A.'])
            ->assertHasErrors(['Deletion failed.']);

        $stored = Requirement::withHistory()->findOrFail($requirement->id);
        $this->assertSame($requirement->description, $stored->description);
        $this->assertSame($prior, $stored->history->all());
        $this->assertNotSoftDeleted($fixture['unknown']);
    }

    public function test_unknown_resolution_rejects_a_question_moved_after_discovery(): void
    {
        $fixture = $this->createProjectFixture();
        $destination = Requirement::factory()->for($fixture['feature'])->create(['description' => '<p>Destination.</p>']);
        $original_description = $fixture['requirement']->description;
        $moved = false;
        Unknown::retrieved(function (Unknown $unknown) use ($fixture, $destination, &$moved): void {
            if (!$moved && $unknown->id === $fixture['unknown']->id) {
                $moved = true;
                $unknown->load('requirement');
                $fixture['unknown']->fresh()->update(['requirement_id' => $destination->id]);
            }
        });

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => 'Answer A.'])
            ->assertHasErrors(['Item not found or cannot be edited.']);

        $this->assertSame($original_description, $fixture['requirement']->fresh()->description);
        $this->assertSame('<p>Destination.</p>', $destination->fresh()->description);
        $this->assertSame($destination->id, $fixture['unknown']->fresh()->requirement_id);
        $this->assertNotSoftDeleted($fixture['unknown']);
    }

    #[DataProvider('requirementEditors')]
    public function test_description_replacement_records_the_current_value_after_a_concurrent_write(string $editor): void
    {
        $fixture = $this->createProjectFixture();
        $fixture['requirement']->update(['description' => '<p>Original.</p>']);
        $concurrent_description = '<p>Original.</p><p>Resolved clarification.</p>';
        $edited = false;
        Requirement::retrieved(function (Requirement $requirement) use ($fixture, $concurrent_description, &$edited): void {
            if (!$edited && $requirement->id === $fixture['requirement']->id) {
                $edited = true;
                $fixture['requirement']->fresh()->update(['description' => $concurrent_description]);
            }
        });

        if ($editor === 'mcp') {
            SpecificationsServer::actingAs($fixture['account'])->tool(RequirementEditTool::class, [
                'id' => $fixture['requirement']->sqid, 'data' => ['description' => 'Replacement.'],
            ])->assertOk();
        } else {
            $this->actingAsAccount($fixture['account']);
            $this->postJson('/api/requirements/' . $fixture['requirement']->sqid . '/edit', [
                'name' => $fixture['requirement']->name,
                'description' => '<p>Replacement.</p>',
                'source' => null,
                'blocked_reason' => null,
                'actor_ids' => [$fixture['projectActor']->sqid],
                'tasks' => [['id' => $fixture['task']->sqid, 'name' => $fixture['task']->name, 'is_complete' => false]],
                'unknowns' => [['id' => $fixture['unknown']->sqid, 'name' => $fixture['unknown']->name]],
            ])->assertOk();
        }

        $requirement = Requirement::withHistory()->findOrFail($fixture['requirement']->id);
        $this->assertStringContainsString('<p>Replacement.</p>', $requirement->description);
        $this->assertSame($concurrent_description, $requirement->history->last()['data']['description']);
    }

    public static function requirementEditors(): array
    {
        return [['mcp'], ['browser']];
    }

    public function test_deletion_event_streams_do_not_reinsert_empty_items_or_cascades(): void
    {
        $this->travelTo('2026-01-01 00:00:00');
        $streams = [];
        $broadcaster = Broadcast::getFacadeRoot();
        foreach (['requirement', 'feature'] as $type) {
            foreach ([false, true] as $has_children) {
                $fixture = $this->createProjectFixture();
                if (!$has_children) {
                    if ($type === 'feature') {
                        $fixture['requirement']->delete();
                    } else {
                        foreach (['assignment', 'task', 'unknown'] as $key) {
                            $fixture[$key]->delete();
                        }
                    }
                }
                $initial = [];
                foreach (['projects', 'actors', 'features', 'requirements', 'assignments', 'tasks', 'unknowns'] as $relation) {
                    $initial[$relation] = $relation === 'projects'
                        ? [$fixture['project']->toResource()->resolve()]
                        : $fixture['project']->{$relation}()->get()->map(fn($item) => $item->withoutRelations()->toResource()->resolve())->all();
                }

                $events = [];
                Broadcast::partialMock()->shouldReceive('event')->andReturnUsing(function ($event) use (&$events) {
                    // Exercise the same immutable payloads delivered by queued broadcasts.
                    $events[] = unserialize(serialize($event));

                    return new PendingBroadcast(app('events'), $event);
                });
                $this->travel(1)->seconds();
                $tool = $type === 'feature' ? FeatureDeleteTool::class : RequirementDeleteTool::class;
                SpecificationsServer::actingAs($fixture['account'])->tool($tool, ['id' => $fixture[$type]->sqid])->assertOk();

                $deleted = [];
                $messages = [];
                foreach ($events as $event) {
                    $payload = $event->broadcastWith();
                    $is_deleted = $event instanceof ProjectItemDeleted;
                    $id = $is_deleted ? $payload['id'] : $payload['data']['id'];
                    $key = $payload['repository'] . ':' . $id;
                    if ($is_deleted) {
                        $deleted[$key] = true;
                    } else {
                        $this->assertArrayNotHasKey($key, $deleted, 'Saved event follows deletion of ' . $key);
                    }
                    $messages[] = ['event' => $is_deleted ? 'ProjectItemDeleted' : 'ProjectItemSaved', ...$payload];
                }
                $this->assertArrayHasKey($type . 's:' . $fixture[$type]->sqid, $deleted);
                $this->assertSame(now()->toISOString(), $fixture['project']->fresh()->activity_at->toISOString());
                if ($type === 'requirement') {
                    $this->assertSame(now()->toISOString(), $fixture['feature']->fresh()->activity_at->toISOString());
                }
                $streams[] = ['initial' => $initial, 'messages' => $messages];
                Broadcast::swap($broadcaster);
            }
        }

        // The frontend test replays these real MCP events against the actual stores.
        if ($path = getenv('SPECTACULAR_EVENT_FIXTURE')) {
            file_put_contents($path, json_encode($streams, JSON_THROW_ON_ERROR));
        }
    }

    private function interleaveDescriptionEdit(array $fixture, string $description): void
    {
        $edited = false;
        Unknown::retrieved(function (Unknown $unknown) use ($fixture, $description, &$edited): void {
            if (!$edited && $unknown->id === $fixture['unknown']->id) {
                $edited = true;
                // The resolver has discovered the question and cached its parent;
                // another writer commits before it obtains the parent lock.
                $unknown->load('requirement');
                $fixture['requirement']->fresh()->update(['description' => $description]);
            }
        });
    }
}

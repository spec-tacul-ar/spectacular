<?php

namespace Tests\Feature\Mcp;

use App\Enums\Role;
use App\Mcp\Servers\SpecificationsServer;
use App\Mcp\Tools\Actors\ActorGetTool;
use App\Mcp\Tools\Assignments\AssignmentGetTool;
use App\Mcp\Tools\Features\FeatureGetTool;
use App\Mcp\Tools\Projects\ProjectGetTool;
use App\Mcp\Tools\Projects\ProjectGetDetailsTool;
use App\Mcp\Tools\Requirements\RequirementGetTool;
use App\Mcp\Tools\Tasks\TaskGetTool;
use App\Mcp\Tools\Unknowns\UnknownGetTool;
use App\Models\Account;
use App\Models\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class EntityReadToolsTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_entity_reads_are_discoverable_without_a_type_selector(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account'], ['mcp:use']);
        $names = [];
        $cursor = null;
        do {
            $response = $this->postJson('/mcp/specifications', [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
                'params' => $cursor ? ['cursor' => $cursor] : new \stdClass(),
            ])->assertOk();
            foreach ($response->json('result.tools') as $tool) {
                $names[] = $tool['name'];
            }
            $cursor = $response->json('result.nextCursor');
        } while ($cursor);

        $this->assertNotContains('GetItemTool', $names);
        $this->assertContains('ProjectGetTool', $names);
        foreach ($this->tools() as $class => $fixture_key) {
            $tool = app($class)->toArray();
            $this->assertContains($tool['name'], $names);
            $this->assertTrue($tool['annotations']['readOnlyHint']);
            $this->assertSame(['id', 'since'], array_keys($tool['inputSchema']['properties']));
            $this->assertSame(['id'], $tool['inputSchema']['required']);
            $this->assertStringContainsString('History entries', $tool['description']);
            $this->assertStringContainsString('read-only', $tool['description']);
        }
    }

    public function test_viewers_can_read_each_entity_with_opaque_ids_and_stored_html(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);
        $fixture['project']->update(['description' => '<p>Project context.</p>']);
        $fixture['feature']->update(['description' => '<p>Feature context.</p>']);
        $fixture['requirement']->update(['description' => '<p>Acceptance criteria.</p>', 'is_draft' => true]);
        $server = SpecificationsServer::actingAs($fixture['account']);

        foreach ($this->tools() as $class => $fixture_key) {
            $item = $fixture[$fixture_key]->fresh();
            $server->tool($class, ['id' => $item->sqid])->assertOk()
                ->assertStructuredContent(fn(AssertableJson $json) => $json
                    ->where('id', $item->sqid)
                    ->etc());
        }
        $server->tool(RequirementGetTool::class, ['id' => $fixture['requirement']->sqid])->assertOk()
            ->assertStructuredContent(fn(AssertableJson $json) => $json
                ->where('description', '<p>Acceptance criteria.</p>')
                ->where('is_draft', true)
                ->where('feature_id', $fixture['feature']->sqid)
                ->has('title')
                ->etc());
        $server->tool(AssignmentGetTool::class, ['id' => $fixture['assignment']->sqid])->assertOk()
            ->assertStructuredContent(fn(AssertableJson $json) => $json
                ->where('actor_id', $fixture['projectActor']->sqid)
                ->where('requirement_id', $fixture['requirement']->sqid)
                ->etc());
        $server->tool(ProjectGetDetailsTool::class, ['id' => $fixture['project']->sqid])->assertOk()
            ->assertStructuredContent(fn(AssertableJson $json) => $json
                ->where('description', '<p>Project context.</p>')
                ->missing('features')
                ->missing('actors')
                ->etc());
        $server->tool(ProjectGetTool::class, ['id' => $fixture['project']->sqid])->assertOk()
            ->assertStructuredContent(fn(AssertableJson $json) => $json
                ->has('features.0.requirements.0')
                ->has('actors.0')
                ->etc());
    }

    public function test_each_entity_read_rejects_unauthorized_missing_and_invalid_ids(): void
    {
        $fixture = $this->createProjectFixture();
        $outsider = Account::factory()->create();
        foreach ($this->tools() as $class => $fixture_key) {
            SpecificationsServer::actingAs($outsider)->tool($class, ['id' => $fixture[$fixture_key]->sqid])->assertHasErrors();
            $server = SpecificationsServer::actingAs($fixture['account']);
            $server->tool($class, ['id' => 'invalid-id'])->assertHasErrors();
            $server->tool($class, [])->assertHasErrors(['id']);
            $server->tool($class, ['id' => $fixture[$fixture_key]->sqid, 'since' => 'invalid-date'])->assertHasErrors(['since']);
        }
    }

    public function test_each_entity_read_filters_history_and_preserves_author_metadata_and_previous_values(): void
    {
        $this->travelTo('2026-01-01 00:00:00');
        $fixture = $this->createProjectFixture();
        $server = SpecificationsServer::actingAs($fixture['account']);
        $first_actor = Actor::factory()->for($fixture['project'])->create();
        $second_actor = Actor::factory()->for($fixture['project'])->create();
        $this->travel(1)->minutes();
        foreach ($this->tools() as $class => $fixture_key) {
            $fixture[$fixture_key]->update($fixture_key === 'assignment' ? ['actor_id' => $first_actor->id] : ['name' => 'First revision']);
        }
        $since = now()->toISOString();
        $this->travel(1)->minutes();
        foreach ($this->tools() as $class => $fixture_key) {
            $item = $fixture[$fixture_key];
            $item->update($fixture_key === 'assignment' ? ['actor_id' => $second_actor->id] : ['name' => 'Second revision']);
            $server->tool($class, ['id' => $item->sqid, 'since' => $since])->assertOk()
                ->assertStructuredContent(fn(AssertableJson $json) => $json
                    ->where('id', $item->sqid)
                    ->count('history', 1)
                    ->where('history.0.account_id', $fixture['account']->sqid)
                    ->where('history.0.is_ai', false)
                    ->where('history.0.data.' . ($fixture_key === 'assignment' ? 'actor_id' : 'name'), $fixture_key === 'assignment' ? $first_actor->sqid : 'First revision')
                    ->has('history.0.timestamp')
                    ->etc());
            $this->assertSame($fixture_key === 'assignment' ? $second_actor->id : 'Second revision', $item->fresh()->getAttribute($fixture_key === 'assignment' ? 'actor_id' : 'name'));
        }
    }

    private function tools(): array
    {
        return [
            ActorGetTool::class => 'projectActor',
            AssignmentGetTool::class => 'assignment',
            FeatureGetTool::class => 'feature',
            ProjectGetDetailsTool::class => 'project',
            RequirementGetTool::class => 'requirement',
            TaskGetTool::class => 'task',
            UnknownGetTool::class => 'unknown',
        ];
    }
}

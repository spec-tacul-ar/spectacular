<?php

namespace Tests\Feature\Mcp;

use App\Enums\Role;
use App\Events\ProjectItemDeleted;
use App\Events\ProjectItemSaved;
use Tests\Support\DirectSpecificationsServer as SpecificationsServer;
use App\Mcp\Tools\Actors\ActorDeleteTool;
use App\Mcp\Tools\Actors\ActorReorderTool;
use App\Mcp\Tools\Assignments\AssignmentCreateTool;
use App\Mcp\Tools\Assignments\AssignmentDeleteTool;
use App\Mcp\Tools\Features\FeatureDeleteTool;
use App\Mcp\Tools\Features\FeatureEditTool;
use App\Mcp\Tools\Projects\ProjectGetChangesTool;
use App\Mcp\Tools\Projects\ProjectCreateTool;
use App\Mcp\Tools\Requirements\RequirementCreateTool;
use App\Mcp\Tools\Requirements\RequirementEditTool;
use App\Mcp\Tools\Requirements\RequirementMoveTool;
use App\Mcp\Tools\Tasks\TaskEditTool;
use App\Mcp\Tools\Unknowns\UnknownResolveTool;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class SpecificationAuthoringTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_authenticated_mcp_transport_exposes_and_executes_write_tools(): void
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
            $names = [...$names, ...collect($response->json('result.tools'))->pluck('name')->all()];
            $cursor = $response->json('result.nextCursor');
        } while ($cursor);
        $this->assertContains('search_tools', $names);
        $this->assertContains('execute_tools', $names);
        $this->assertNotContains('feature-create', $names);
        $search = $this->postJson('/mcp/specifications', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'search_tools', 'arguments' => ['query' => 'feature-create']],
        ])->assertOk();
        $catalog = json_decode($search->json('result.content.0.text'), true);
        $this->assertSame('feature-create', $catalog['tools'][0]['name']);
        $response = $this->postJson('/mcp/specifications', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'execute_tools', 'arguments' => ['calls' => [[
                'name' => 'feature-create', 'arguments' => [
                    'project_id' => $fixture['project']->sqid, 'name' => 'Ordering',
                ],
            ]]]],
        ])->assertOk();
        $this->assertStringContainsString('\"ok\":true', $response->streamedContent());
        $this->assertDatabaseHas('features', ['project_id' => $fixture['project']->id, 'name' => 'Ordering']);
    }

    public function test_actor_create_validates_decoded_project_and_returns_the_new_sqid(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(\App\Mcp\Tools\Actors\ActorCreateTool::class, [
            'project_id' => $fixture['project']->sqid,
            'name' => 'New actors',
        ])->assertOk()->assertStructuredContent(fn(AssertableJson $json) => $json
            ->where('success', true)
            ->where('id', $fixture['project']->actors()->where('name', 'New actors')->firstOrFail()->sqid));

        $server->tool(\App\Mcp\Tools\Actors\ActorCreateTool::class, [
            'project_id' => 'invalid-id',
            'name' => 'Invalid actors',
        ])->assertHasErrors();
        $this->assertDatabaseMissing('actors', ['name' => 'Invalid actors']);
    }

    public function test_action_backed_create_accepts_an_opaque_parent_id_and_returns_success(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(\App\Mcp\Tools\Features\FeatureCreateTool::class, [
                'project_id' => $fixture['project']->sqid,
                'name' => 'New capability',
            ])
            ->assertOk()
            ->assertStructuredContent(fn(AssertableJson $json) => $json->where('success', true)
                ->where('id', $fixture['project']->features()->where('name', 'New capability')->firstOrFail()->sqid));

        $this->assertDatabaseHas('features', ['project_id' => $fixture['project']->id, 'name' => 'New capability']);
    }

    public function test_write_tools_publish_schemas_with_detailed_guidance_and_write_annotations(): void
    {
        $server = app()->make(SpecificationsServer::class, ['transport' => new \Laravel\Mcp\Server\Transport\FakeTransporter()]);
        $this->assertStringContainsString('historical revisions', $server->createContext()->instructions);
        foreach ($server->createContext()->tools() as $instance) {
            $tool = $instance->toArray();
            if ($tool['annotations']['readOnlyHint']) {
                continue;
            }
            $this->assertArrayNotHasKey('type', $tool['inputSchema']['properties']);
            $this->assertFalse($tool['annotations']['openWorldHint']);
            $this->assertNotEmpty($tool['inputSchema']['properties']);
            $this->assertGreaterThan(200, strlen($tool['description']));
        }
        $schema = app(RequirementEditTool::class)->toArray()['inputSchema']['properties'];
        $this->assertArrayNotHasKey('history', $schema);
        $this->assertStringContainsString('action phrase', $schema['name']['description']);
        $this->assertStringContainsString('HTML', $schema['description']['description']);
    }

    public function test_each_entity_schema_contains_only_its_own_fields(): void
    {
        $expected = [
            'project' => ['name', 'description', 'locale'],
            'actor' => ['name', 'summary'],
            'feature' => ['name', 'description'],
            'requirement' => ['name', 'description', 'source', 'blocked_reason', 'is_draft'],
            'task' => ['name', 'is_complete'],
            'unknown' => ['name'],
            'assignment' => ['actor_id'],
        ];
        foreach ($expected as $type => $fields) {
            $operations = $type === 'assignment' ? ['Create'] : ['Create', 'Edit'];
            foreach ($operations as $operation) {
                $tool = app($this->toolClass($operation, $type))->toArray();
                $schema = $tool['inputSchema']['properties'];
                $target = $operation === 'Edit' ? 'id' : ($type === 'project' ? null : $this->parentField($type));
                $this->assertSame($fields, array_values(array_diff(array_keys($schema), array_filter([$target]))));
                $this->assertArrayNotHasKey('type', $schema);
                if ($operation === 'Create') {
                    $this->assertContains($type === 'assignment' ? 'actor_id' : 'name', $tool['inputSchema']['required']);
                    if ($type !== 'project') {
                        $this->assertContains($this->parentField($type), $tool['inputSchema']['required']);
                    }
                }
            }
        }
    }

    public function test_each_entity_edit_preserves_unrelated_fields(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $server = SpecificationsServer::actingAs($fixture['account']);
        foreach (['project', 'actor', 'feature', 'requirement', 'task', 'unknown'] as $type) {
            $item = $fixture[$type === 'actor' ? 'projectActor' : $type];
            $server->tool($this->toolClass('Edit', $type), ['id' => $item->sqid, 'name' => 'Updated name'])->assertOk();
            $this->assertSame('Updated name', $item->fresh()->name);
            $server->tool($this->toolClass('Edit', $type), ['id' => $item->sqid, 'history' => [], 'actor_id' => $fixture['projectActor']->sqid])->assertOk();
            $this->assertSame('Updated name', $item->fresh()->name);
        }
        $this->assertSame(1, $fixture['requirement']->tasks()->count());
        $this->assertSame(1, $fixture['requirement']->unknowns()->count());
        $this->assertSame(1, $fixture['requirement']->assignments()->count());
    }

    public function test_changes_for_items_created_and_removed_since_checkpoint_are_tombstones(): void
    {
        $this->travelTo('2026-01-01 00:00:00');
        $project = Project::factory()->create();
        $account = Account::factory()->create();
        $this->attachCollaboration($account, $project);
        $since = now()->toISOString();
        $this->travel(1)->minutes();
        $fixture_feature = \App\Models\Feature::factory()->for($project)->create();
        $requirement = \App\Models\Requirement::factory()->for($fixture_feature)->create();
        $task = \App\Models\Task::factory()->for($requirement)->create();
        $this->travel(1)->minutes();
        $server = SpecificationsServer::actingAs($account);
        $server->tool(FeatureDeleteTool::class, ['id' => $fixture_feature->sqid])->assertOk();
        $server->tool(ProjectGetChangesTool::class, ['id' => $project->sqid, 'since' => $since])->assertOk()
            ->assertStructuredContent(fn(AssertableJson $json) => $json
                ->where('features.0.id', $fixture_feature->sqid)
                ->has('features.0.deleted_at')
                ->missing('features.0.name')
                ->count('requirements', 0)
                ->count('tasks', 0)
                ->etc());
        $this->assertSoftDeleted($requirement);
        $this->assertSoftDeleted($task);
    }

    public function test_html_is_preserved_and_length_limits_are_enforced(): void
    {
        $fixture = $this->createProjectFixture();
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid,
            'description' => '<p><strong>Good</strong></p>',
        ])->assertOk();
        $description = $fixture['requirement']->fresh()->description;
        $this->assertStringContainsString('<strong>Good</strong>', $description);
        foreach ([['name' => str_repeat('x', 251)], ['description' => str_repeat('x', 10001)], ['name' => null]] as $data) {
            $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid, ...$data])->assertHasErrors();
        }
    }

    public function test_author_can_build_a_specification_with_generated_titles_and_references(): void
    {
        $account = Account::factory()->create();
        $server = SpecificationsServer::actingAs($account);
        $server->tool(ProjectCreateTool::class, [
            'name' => "Lucia's Restaurant", 'description' => '<p><strong>Italian</strong> restaurant ordering system.</p>',
        ])->assertOk();
        $project = Project::firstOrFail();
        $this->assertTrue($account->owns($project));
        $this->assertStringContainsString('<strong>Italian</strong>', $project->description);

        foreach (['actor' => 'Waiters', 'feature' => 'Orders'] as $type => $name) {
            $server->tool($this->toolClass('Create', $type), [$this->parentField($type) => $project->sqid, 'name' => $name])->assertOk();
        }
        $feature = Feature::firstOrFail();
        $actor = Actor::firstOrFail();
        $server->tool(RequirementCreateTool::class, ['feature_id' => $feature->sqid,
            'name' => 'take orders', 'description' => 'A table number is required.', 'is_draft' => true,
        ])->assertOk();
        $requirement = Requirement::firstOrFail();
        $this->assertSame(1, $requirement->reference);
        $this->assertTrue($requirement->is_draft);
        $assignment = ['requirement_id' => $requirement->sqid, 'actor_id' => $actor->sqid];
        $server->tool(AssignmentCreateTool::class, $assignment)->assertOk();
        $server->tool(AssignmentCreateTool::class, $assignment)->assertOk();
        $this->assertSame(1, $requirement->assignments()->count());
        $this->assertSame('Waiters can take orders', $requirement->fresh()->title);

        foreach (['task' => 'Build the order form', 'unknown' => 'Can an order include multiple tables?'] as $type => $name) {
            $server->tool($this->toolClass('Create', $type), [$this->parentField($type) => $requirement->sqid, 'name' => $name])->assertOk();
        }
        $this->assertSame(1, $requirement->tasks()->count());
        $this->assertSame(1, $requirement->unknowns()->count());
    }

    public function test_partial_edits_preserve_children_and_append_ai_attributed_history(): void
    {
        $fixture = $this->createProjectFixture();
        $requirement = $fixture['requirement'];
        $original = $requirement->name;
        $requirement->update(['source' => 'Kickoff meeting']);
        $before = $requirement->history->all();
        SpecificationsServer::actingAs($fixture['account'])->tool(RequirementEditTool::class, [
            'id' => $requirement->sqid,
            'name' => 'take orders', 'description' => '<p><strong>Table number</strong> is required.</p>', 'blocked_reason' => null,
        ])->assertOk();
        $requirement = Requirement::withHistory()->findOrFail($requirement->id);
        $this->assertSame('Kickoff meeting', $requirement->source);
        $this->assertSame(1, $requirement->tasks()->count());
        $this->assertSame(1, $requirement->unknowns()->count());
        $this->assertSame(1, $requirement->assignments()->count());
        $this->assertSame($before, array_slice($requirement->history->all(), 0, count($before)));
        $revision = $requirement->history->last();
        $this->assertSame($original, $revision['data']['name']);
        $this->assertSame($fixture['account']->sqid, $revision['account_id']);
        $this->assertTrue($revision['is_ai']);
        $requirement->update(['name' => 'take orders again']);
        $this->assertFalse($requirement->history->last()['is_ai']);
    }

    public function test_write_tools_reject_read_only_accounts_outsiders_and_archived_projects(): void
    {
        foreach ([Role::VIEWER, Role::EDITOR] as $role) {
            $fixture = $this->createProjectFixture($role);
            if ($role === Role::EDITOR) {
                $fixture['project']->archive();
            }
            foreach ([$fixture['account'], Account::factory()->create()] as $account) {
                $server = SpecificationsServer::actingAs($account);
                foreach (['project', 'actor', 'feature', 'requirement', 'task', 'unknown', 'assignment'] as $type) {
                    $item = $fixture[$type === 'actor' ? 'projectActor' : $type];
                    if ($type !== 'project') {
                        $parent_field = $this->parentField($type);
                        $parent_type = str_replace('_id', '', $parent_field);
                        $data = $type === 'assignment' ? ['actor_id' => $fixture['projectActor']->sqid] : ['name' => 'Hidden'];
                        $server->tool($this->toolClass('Create', $type), [$parent_field => $fixture[$parent_type]->sqid, ...$data])->assertHasErrors();
                        $server->tool($this->toolClass('Delete', $type), ['id' => $item->sqid])->assertHasErrors();
                    }
                    if ($type !== 'assignment') {
                        $server->tool($this->toolClass('Edit', $type), ['id' => $item->sqid, 'name' => 'Hidden'])->assertHasErrors();
                    }
                    if (in_array($type, ['requirement', 'task', 'unknown'])) {
                        $parent_field = $this->parentField($type);
                        $parent_type = str_replace('_id', '', $parent_field);
                        $server->tool($this->toolClass('Move', $type), ['id' => $item->sqid, $parent_field => $fixture[$parent_type]->sqid])->assertHasErrors();
                    }
                    if (in_array($type, ['actor', 'feature', 'requirement', 'task'])) {
                        $parent_field = $this->parentField($type);
                        $parent_type = str_replace('_id', '', $parent_field);
                        $server->tool($this->toolClass('Reorder', $type), [$parent_field => $fixture[$parent_type]->sqid, 'ids' => [$item->sqid]])->assertHasErrors();
                    }
                }
                $server->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => 'Established answer'])->assertHasErrors();
            }
        }
    }

    public function test_editor_can_write_and_draft_editing_is_allowed_but_completed_drafts_are_rejected(): void
    {
        $this->travelTo('2026-01-01 00:00:00');
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid, 'is_draft' => true, 'blocked_reason' => null])->assertOk();
        $this->travel(1)->minutes();
        $fixture['requirement']->fresh()->complete();
        $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid, 'is_draft' => true])->assertHasErrors(['Completed requirements cannot be marked as draft.']);
        $this->assertFalse($fixture['requirement']->fresh()->is_draft);
    }

    public function test_requirement_edit_does_not_modify_nested_items_from_unadvertised_fields(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $server = SpecificationsServer::actingAs($fixture['account']);

        $server->tool(RequirementEditTool::class, [
            'id' => $fixture['requirement']->sqid,
            'tasks' => [['id' => $fixture['task']->id, 'name' => 'Changed task']],
            'unknowns' => [['id' => $fixture['unknown']->id, 'name' => 'Changed question']],
            'actor_ids' => [],
        ])->assertOk();

        $this->assertSame($fixture['task']->name, $fixture['task']->fresh()->name);
        $this->assertSame($fixture['unknown']->name, $fixture['unknown']->fresh()->name);
        $this->assertDatabaseHas('assignments', ['id' => $fixture['assignment']->id, 'deleted_at' => null]);
    }

    public function test_requirement_edit_rejects_children_from_a_different_requirement(): void
    {
        $fixture = $this->createProjectFixture(Role::EDITOR);
        $other = Requirement::factory()->for($fixture['feature'])->create();
        $task = \App\Models\Task::factory()->for($other)->create(['name' => 'Other task']);
        $unknown = \App\Models\Unknown::factory()->for($other)->create(['name' => 'Other question']);

        SpecificationsServer::actingAs($fixture['account'])->tool(RequirementEditTool::class, [
            'id' => $fixture['requirement']->sqid,
            'tasks' => [['id' => $task->id, 'name' => 'Hijacked task']],
            'unknowns' => [['id' => $unknown->id, 'name' => 'Hijacked question']],
        ])->assertHasErrors();

        $this->assertSame('Other task', $task->fresh()->name);
        $this->assertSame('Other question', $unknown->fresh()->name);
    }

    public function test_feedback_and_history_fields_are_never_writable(): void
    {
        $fixture = $this->createProjectFixture();
        $server = SpecificationsServer::actingAs($fixture['account']);
        $this->actingAsAccount($fixture['account'], ['mcp:use']);
        $context = app()->make(SpecificationsServer::class, ['transport' => new \Laravel\Mcp\Server\Transport\FakeTransporter()])->createContext();
        $names = $context->tools()->map(fn($tool) => $tool->name())->all();
        foreach (['CreateItemTool', 'EditItemTool', 'DeleteItemTool', 'MoveItemTool', 'ReorderItemsTool', 'ProjectDeleteTool', 'CommentCreateTool', 'CommentEditTool', 'CommentDeleteTool', 'AccountEditTool', 'CollaborationEditTool'] as $name) {
            $this->assertNotContains($name, $names);
        }
        foreach (['history', 'uuid', 'created_at', 'updated_at', 'completed_at', 'deleted_at', 'reference', 'comments', 'is_complete', 'summary', 'actor_id', 'locale'] as $field) {
            $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid, $field => 'Forbidden'])->assertOk();
        }
        $this->assertSame($fixture['requirement']->name, $fixture['requirement']->fresh()->name);
        $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid, 'tasks' => 'Forbidden'])->assertHasErrors();
        $server->tool(RequirementEditTool::class, ['id' => $fixture['requirement']->sqid, 'feature_id' => 'Forbidden'])->assertOk();
        $this->assertSame($fixture['feature']->id, $fixture['requirement']->fresh()->feature_id);
        $server->tool(TaskEditTool::class, ['id' => 'invalid-id', 'name' => 'Forbidden'])->assertHasErrors(['Item not found or cannot be edited.']);
        $server->tool(TaskEditTool::class, ['id' => $fixture['task']->sqid])->assertOk();
        $this->assertSame(1, $fixture['requirement']->tasks()->count());
    }

    public function test_removals_retain_feedback_and_history_and_are_visible_in_deltas(): void
    {
        $this->travelTo('2026-01-01 00:00:00');
        $fixture = $this->createProjectFixture();
        $comments = collect([$fixture['feature'], $fixture['requirement']])->map(fn($item) => Comment::factory()->for($fixture['account'])->for($fixture['project'])->create([
            'commentable_type' => $item->getMorphClass(), 'commentable_id' => $item->id,
        ]));
        $since = now()->toISOString();
        $this->travel(1)->minutes();
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(ActorDeleteTool::class, ['id' => $fixture['projectActor']->sqid])->assertOk();
        $this->assertSoftDeleted($fixture['assignment']);
        $server->tool(FeatureDeleteTool::class, ['id' => $fixture['feature']->sqid])->assertOk();
        foreach (['feature', 'requirement', 'task', 'unknown', 'assignment'] as $key) {
            $this->assertSoftDeleted($fixture[$key]);
            $item = $fixture[$key]->newQuery()->withTrashed()->withHistory()->findOrFail($fixture[$key]->id);
            $this->assertTrue($item->history->last()['is_ai']);
        }
        foreach ($comments as $comment) {
            $this->assertDatabaseHas('comments', ['id' => $comment->id]);
        }
        $server->tool(ProjectGetChangesTool::class, ['id' => $fixture['project']->sqid, 'since' => $since])
            ->assertOk()->assertStructuredContent(fn(AssertableJson $json) => $json->has('features.0.deleted_at')->count('requirements', 0)->count('tasks', 0)->count('unknowns', 0)->etc());
        $server->tool(FeatureEditTool::class, ['id' => $fixture['feature']->sqid, 'name' => 'Resurrected'])->assertHasErrors();
    }

    public function test_assignments_reject_cross_specification_actors_and_preserve_detachment_history(): void
    {
        $fixture = $this->createProjectFixture();
        $other = $this->createProjectFixture(account: $fixture['account']);
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(AssignmentCreateTool::class, ['requirement_id' => $fixture['requirement']->sqid, 'actor_id' => $other['projectActor']->sqid])->assertHasErrors(['must belong']);
        $server->tool(AssignmentDeleteTool::class, ['id' => $fixture['assignment']->sqid])->assertOk();
        $server->tool(AssignmentCreateTool::class, ['requirement_id' => $fixture['requirement']->sqid, 'actor_id' => $fixture['projectActor']->sqid])->assertOk();
        $this->assertSame(2, $fixture['requirement']->assignments()->withTrashed()->count());
    }

    public function test_moves_preserve_identity_and_children_and_reject_other_specifications(): void
    {
        $fixture = $this->createProjectFixture();
        $feature = Feature::factory()->for($fixture['project'])->create();
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(RequirementMoveTool::class, ['id' => $fixture['requirement']->sqid, 'feature_id' => $feature->sqid])->assertOk();
        $requirement = $fixture['requirement']->fresh();
        $this->assertSame($feature->id, $requirement->feature_id);
        $this->assertSame($fixture['requirement']->reference, $requirement->reference);
        $this->assertSame(1, $requirement->tasks()->count());
        $other = $this->createProjectFixture(account: $fixture['account']);
        $server->tool(RequirementMoveTool::class, ['id' => $requirement->sqid, 'feature_id' => $other['feature']->sqid])->assertHasErrors(['same specification']);
        $destination = Requirement::factory()->for($feature)->create();
        foreach (['task', 'unknown'] as $type) {
            $server->tool($this->toolClass('Move', $type), ['id' => $fixture[$type]->sqid, $this->parentField($type) => $destination->sqid])->assertOk();
            $this->assertSame($destination->id, $fixture[$type]->fresh()->requirement_id);
        }
    }

    public function test_reordering_rejects_invalid_lists_without_partial_writes(): void
    {
        $fixture = $this->createProjectFixture();
        $other = Actor::factory()->for($fixture['project'])->create();
        $actor = $fixture['projectActor'];
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(ActorReorderTool::class, ['project_id' => $fixture['project']->sqid, 'ids' => [$other->sqid, $actor->sqid]])->assertOk();
        $this->assertSame(0, $other->fresh()->weight);
        $this->assertSame(1, $actor->fresh()->weight);
        foreach ([[$actor->sqid], [$actor->sqid, $actor->sqid], [$actor->sqid, 'invalid'], []] as $ids) {
            $server->tool(ActorReorderTool::class, ['project_id' => $fixture['project']->sqid, 'ids' => $ids])->assertHasErrors();
            $this->assertSame(0, $other->fresh()->weight);
            $this->assertSame(1, $actor->fresh()->weight);
        }
    }

    public function test_each_reorder_tool_uses_the_correct_parent_collection(): void
    {
        $fixture = $this->createProjectFixture();
        $server = SpecificationsServer::actingAs($fixture['account']);
        foreach (['actor', 'feature', 'requirement', 'task'] as $type) {
            $parent_field = $this->parentField($type);
            $parent_type = str_replace('_id', '', $parent_field);
            $parent = $fixture[$parent_type];
            $item = $fixture[$type === 'actor' ? 'projectActor' : $type];
            $other = $parent->{$type . 's'}()->create(['name' => 'Second sibling']);
            $server->tool($this->toolClass('Reorder', $type), [$parent_field => $parent->sqid, 'ids' => [$other->sqid, $item->sqid]])->assertOk();
            $this->assertSame(0, $other->fresh()->weight);
            $this->assertSame(1, $item->fresh()->weight);
        }
    }

    public function test_each_delete_tool_soft_deletes_only_its_specification_scope(): void
    {
        foreach (['actor', 'feature', 'requirement', 'task', 'unknown', 'assignment'] as $type) {
            $fixture = $this->createProjectFixture();
            $server = SpecificationsServer::actingAs($fixture['account']);
            if ($type === 'actor') {
                $server->tool(AssignmentDeleteTool::class, ['id' => $fixture['assignment']->sqid])->assertOk();
            }
            $item = $fixture[$type === 'actor' ? 'projectActor' : $type];
            $server->tool($this->toolClass('Delete', $type), ['id' => $item->sqid])->assertOk();
            $this->assertSoftDeleted($item);
            $this->assertNotSoftDeleted($fixture['project']);
            if ($type !== 'feature') {
                $this->assertNotSoftDeleted($fixture['feature']);
            }
            if (in_array($type, ['task', 'unknown', 'assignment', 'actor'])) {
                $this->assertNotSoftDeleted($fixture['requirement']);
            }
        }
    }

    public function test_unknown_resolution_retains_description_and_rejects_oversized_answers_atomically(): void
    {
        $fixture = $this->createProjectFixture();
        $fixture['requirement']->update(['description' => '<p>Existing acceptance criteria.</p>']);
        $server = SpecificationsServer::actingAs($fixture['account']);
        $server->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => str_repeat('x', 9999)])->assertHasErrors();
        $this->assertNotSoftDeleted($fixture['unknown']);
        $server->tool(UnknownResolveTool::class, ['id' => $fixture['unknown']->sqid, 'clarification' => '<p>Each order belongs to <strong>one table</strong>.</p>'])->assertOk();
        $description = $fixture['requirement']->fresh()->description;
        $this->assertStringContainsString('Existing acceptance criteria.', $description);
        $this->assertStringContainsString('<strong>one table</strong>', $description);
        $this->assertSoftDeleted($fixture['unknown']);
    }

    public function test_queued_events_keep_channels_and_payloads_after_parent_deletion(): void
    {
        $fixture = $this->createProjectFixture();
        $saved = new ProjectItemSaved($fixture['requirement']->load('tasks', 'assignments', 'unknowns'));
        $deleted = new ProjectItemDeleted($fixture['task']);
        $fixture['feature']->delete();
        foreach ([$saved, $deleted] as $event) {
            $event = unserialize(serialize($event));
            $this->assertSame('private-projects.' . $fixture['project']->sqid, $event->broadcastOn()[0]->name);
        }
        $this->assertSame($fixture['task']->sqid, $deleted->broadcastWith()['id']);
        $this->assertArrayNotHasKey('tasks', $saved->broadcastWith()['data']);
        $this->assertArrayNotHasKey('assignments', $saved->broadcastWith()['data']);
        $this->assertArrayNotHasKey('unknowns', $saved->broadcastWith()['data']);
    }
    private function toolClass(string $operation, string $type): string
    {
        $entity = ucfirst($type);
        $name = $entity . $operation . 'Tool';

        return 'App\\Mcp\\Tools\\' . $entity . 's\\' . $name;
    }

    private function parentField(string $type): string
    {
        return match ($type) {
            'actor', 'feature' => 'project_id',
            'requirement' => 'feature_id',
            default => 'requirement_id',
        };
    }

}

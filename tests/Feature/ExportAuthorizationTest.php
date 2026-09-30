<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\Unknown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class ExportAuthorizationTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_project_json_export_requires_authentication(): void
    {
        Account::factory()->create();
        $project = Project::factory()->create();

        $this->getJson('/exports/' . $project->sqid . '/json')->assertForbidden();
    }

    public function test_project_json_export_rejects_non_members(): void
    {
        $fixture = $this->createProjectFixture();
        $stranger = Account::factory()->create();

        $this->actingAs($stranger);

        $this->getJson('/exports/' . $fixture['project']->sqid . '/json')->assertNotFound();
    }

    public function test_project_json_export_allows_project_members(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $fixture = $this->createProjectFixture(Role::VIEWER);
        $fixture['project']->refresh();
        $fixture['requirement']->update(['blocked_reason' => null]);

        $this->travelTo('2026-08-18 10:01:00');
        $fixture['requirement']->complete();
        $this->actingAs($fixture['account']);

        $response = $this->getJson('/exports/' . $fixture['project']->sqid . '/json');

        $response->assertOk();
        $response->assertJsonPath('name', $fixture['project']->name);
        $response->assertJsonPath('locale', $fixture['project']->locale);
        $response->assertJsonMissingPath('features.0.requirements.0.completed_at');
        $response->assertJsonMissingPath('features.0.requirements.0.activity_at');

        $this->get('/exports/' . $fixture['project']->sqid . '/html')
            ->assertOk()
            ->assertSeeText('Complete');

        $this->get('/exports/' . $fixture['project']->sqid . '/markdown')
            ->assertOk()
            ->assertSee('[Complete]');

        $this->travelBack();
    }

    public function test_project_exports_only_return_the_requested_project(): void
    {
        $account = Account::factory()->create();
        $visibleProject = Project::factory()->create([
            'name' => 'Visible Export Project',
            'description' => 'Visible export description',
        ]);
        $hiddenProject = Project::factory()->create([
            'name' => 'Hidden Export Project',
            'description' => 'Hidden export description',
        ]);

        $this->attachCollaboration($account, $visibleProject, Role::VIEWER);
        $this->buildExportData($visibleProject);
        $this->buildExportData($hiddenProject);

        $this->actingAs($account);

        $jsonResponse = $this->getJson('/exports/' . $visibleProject->sqid . '/json');

        $jsonResponse->assertOk();
        $jsonResponse->assertJsonPath('name', 'Visible Export Project');
        $jsonResponse->assertJsonMissing(['name' => 'Hidden Export Project']);

        $this->get('/exports/' . $visibleProject->sqid . '/html')
            ->assertOk()
            ->assertSeeText('Visible Export Project')
            ->assertDontSeeText('Hidden Export Project');

        $this->get('/exports/' . $visibleProject->sqid . '/markdown')
            ->assertOk()
            ->assertSeeText('Visible Export Project')
            ->assertDontSeeText('Hidden Export Project');
    }

    public function test_project_exports_do_not_leak_to_non_members(): void
    {
        $member = Account::factory()->create();
        $outsider = Account::factory()->create();
        $project = Project::factory()->create();

        $this->attachCollaboration($member, $project, Role::VIEWER);

        $this->actingAs($outsider);

        foreach (['json', 'html', 'markdown'] as $type) {
            $this->get('/exports/' . $project->sqid . '/' . $type)->assertNotFound();
        }
    }

    public function test_project_exports_set_the_expected_content_type_headers(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);
        $this->actingAs($fixture['account']);

        $this->get('/exports/' . $fixture['project']->sqid . '/json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');

        $this->get('/exports/' . $fixture['project']->sqid . '/html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->get('/exports/' . $fixture['project']->sqid . '/markdown')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    }

    public function test_html_and_markdown_exports_use_the_project_locale(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $account = Account::factory()->create();
        $project = Project::factory()->create(['locale' => 'fr']);
        $feature = Feature::factory()->for($project)->create(['name' => 'Export Feature']);

        $first_actor = Actor::factory()->for($project)->create([
            'name' => 'First Export Actor',
            'weight' => 1,
        ]);
        $second_actor = Actor::factory()->for($project)->create([
            'name' => 'Second Export Actor',
            'weight' => 2,
        ]);

        $blocked_requirement = Requirement::factory()->for($feature)->create([
            'name' => 'perform blocked action',
            'blocked_reason' => 'Waiting on dependency',
            'source' => 'Project brief',
        ]);
        $blocked_requirement->assignments()->createMany([
            ['actor_id' => $first_actor->id],
            ['actor_id' => $second_actor->id],
        ]);
        Unknown::factory()->for($blocked_requirement)->create(['name' => 'Open question']);

        $complete_requirement = Requirement::factory()->for($feature)->create([
            'name' => 'perform complete action',
            'blocked_reason' => null,
        ]);
        Task::factory()->for($complete_requirement)->create([
            'name' => 'Export Task',
            'is_complete' => true,
        ]);
        $this->travelTo('2026-08-18 10:01:00');
        $complete_requirement->complete();

        $this->attachCollaboration($account, $project, Role::VIEWER);
        $this->actingAs($account);

        $this->get('/exports/' . $project->sqid . '/html')
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSeeText('Utilisateurs')
            ->assertSeeText('Fonctionnalités')
            ->assertSeeText('First Export Actor et Second Export Actor peuvent perform blocked action')
            ->assertSeeText('Bloqué: Waiting on dependency')
            ->assertSeeText('Source: Project brief')
            ->assertSeeText('Questions en suspens')
            ->assertSeeText('Tâches')
            ->assertSeeText('Terminé');

        $this->get('/exports/' . $project->sqid . '/markdown')
            ->assertOk()
            ->assertSee('## Utilisateurs', false)
            ->assertSee('## Fonctionnalités', false)
            ->assertSee('#### (' . $blocked_requirement->reference . ') First Export Actor et Second Export Actor peuvent perform blocked action', false)
            ->assertSee('**[Bloqué] Waiting on dependency**', false)
            ->assertSee('*Source: Project brief*', false)
            ->assertSee('##### Questions en suspens', false)
            ->assertSee('#### (' . $complete_requirement->reference . ') Utilisateurs peuvent perform complete action [Terminé]', false)
            ->assertSee('##### Tâches', false)
            ->assertSee('* Export Task [Terminé]', false);

        $this->assertSame('en', app()->getLocale());
        $this->travelBack();
    }

    public function test_project_exports_include_requirement_references(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);
        $requirement = $fixture['requirement'];
        $requirement->reference = 42;
        $requirement->saveQuietly();

        $this->actingAs($fixture['account']);

        $this->get('/exports/' . $fixture['project']->sqid . '/html')
            ->assertOk()
            ->assertSeeText('Ref: 42');

        $this->get('/exports/' . $fixture['project']->sqid . '/markdown')
            ->assertOk()
            ->assertSee('#### (42) ' . $requirement->title, false);

        $this->getJson('/exports/' . $fixture['project']->sqid . '/json')
            ->assertOk()
            ->assertJsonPath('features.0.requirements.0.reference', 42);
    }

    public function test_html_and_markdown_exports_do_not_lazy_load_relations(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);
        $this->actingAs($fixture['account']);

        Model::preventLazyLoading();

        try {
            $this->get('/exports/' . $fixture['project']->sqid . '/html')->assertOk();
            $this->get('/exports/' . $fixture['project']->sqid . '/markdown')->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_project_export_route_requires_authentication_and_allows_members(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);

        $this->getJson('/exports/' . $fixture['project']->sqid . '/json')->assertForbidden();

        $this->actingAs($fixture['account']);

        $this->getJson('/exports/' . $fixture['project']->sqid . '/json')
            ->assertOk()
            ->assertJsonPath('name', $fixture['project']->name);
    }

    public function test_project_exports_reject_unknown_export_types(): void
    {
        $fixture = $this->createProjectFixture(Role::VIEWER);
        $this->actingAs($fixture['account']);

        $this->get('/exports/' . $fixture['project']->sqid . '/xml')->assertNotFound();
    }

    private function buildExportData(Project $project): void
    {
        $actor = Actor::factory()->for($project)->create(['name' => 'Export Actor']);
        $feature = Feature::factory()->for($project)->create(['name' => 'Export Feature']);
        $requirement = Requirement::factory()->for($feature)->create(['name' => 'export requirement']);

        $requirement->assignments()->create(['actor_id' => $actor->id]);
        Task::factory()->for($requirement)->create(['name' => 'Export Task']);
        Unknown::factory()->for($requirement)->create(['name' => 'Export Unknown?']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class DraftExportTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_json_exports_include_boolean_draft_status_and_imports_preserve_it(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAs($fixture['account']);

        foreach ([true, false] as $isDraft) {
            $fixture['requirement']->update(['is_draft' => $isDraft]);
            $response = $this->getJson('/exports/' . $fixture['project']->sqid . '/json')
                ->assertOk()
                ->assertJsonPath('features.0.requirements.0.is_draft', $isDraft)
                ->assertJsonPath('features.0.requirements.0.activity_at', $fixture['requirement']->activity_at->toJSON())
                ->assertJsonPath('features.0.requirements.0.completed_at', null);

            $imported = Project::import($response->json());
            $this->assertSame($isDraft, $imported->features->first()->requirements->first()->is_draft);
        }
    }

    public function test_older_imports_default_to_non_draft_and_invalid_draft_values_are_rejected(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAs($fixture['account']);
        $data = $this->getJson('/exports/' . $fixture['project']->sqid . '/json')->assertOk()->json();
        unset($data['features'][0]['requirements'][0]['is_draft']);

        $imported = Project::import($data);
        $this->assertFalse($imported->features->first()->requirements->first()->is_draft);

        $data['features'][0]['requirements'][0]['is_draft'] = 'draft';
        $this->expectException(ValidationException::class);
        Project::import($data);
    }

    public function test_draft_exports_prioritize_draft_and_preserve_blocked_reasons(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAs($fixture['account']);
        $fixture['requirement']->update(['is_draft' => true]);

        foreach ([false, true] as $complete) {
            $fixture['task']->update(['is_complete' => $complete]);

            foreach ([null, 'Waiting for details'] as $reason) {
                $fixture['requirement']->update(['blocked_reason' => $reason]);

                $html = $this->get('/exports/' . $fixture['project']->sqid . '/html')
                    ->assertOk()
                    ->assertSee('class="requirement requirement-draft"', false)
                    ->assertSee('<span class="status status-draft">Draft</span>', false)
                    ->assertDontSee('<span class="status status-blocked">', false);

                preg_match('/<h4>(.*?)<\/h4>/s', $html->getContent(), $heading);
                $this->assertStringNotContainsString('status-complete', $heading[1]);

                $markdown = $this->get('/exports/' . $fixture['project']->sqid . '/markdown')
                    ->assertOk()->assertSee('[Draft]', false);
                preg_match('/^#### .*$/m', $markdown->getContent(), $heading);
                $this->assertStringNotContainsString('[Complete]', $heading[0]);

                if ($reason) {
                    $this->assertMatchesRegularExpression('/<p class="blocker">\s*<strong>Blocked:<\/strong>\s*Waiting for details\s*<\/p>/', $html->getContent());
                    $markdown->assertSee('**[Blocked] Waiting for details**', false);
                }
            }
        }
    }

    public function test_non_draft_exports_keep_their_existing_status_markers(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAs($fixture['account']);
        $fixture['task']->update(['is_complete' => true]);
        $fixture['requirement']->update(['blocked_reason' => null]);
        $this->travel(1)->minutes();
        $fixture['requirement']->complete();

        $this->get('/exports/' . $fixture['project']->sqid . '/html')
            ->assertOk()
            ->assertSee('<span class="status status-complete">Complete</span>', false)
            ->assertDontSee('<span class="status status-draft">', false);
        $this->get('/exports/' . $fixture['project']->sqid . '/markdown')
            ->assertOk()->assertSee('[Complete]', false)->assertDontSee('[Draft]', false);

        $fixture['requirement']->update(['blocked_reason' => 'Waiting for details']);
        $this->get('/exports/' . $fixture['project']->sqid . '/html')
            ->assertOk()->assertSee('<span class="status status-blocked">Blocked</span>', false);
    }

    public function test_exports_round_trip_completion_and_stale_draft_timestamps(): void
    {
        $this->travelTo('2026-08-18 10:00:00');
        $fixture = $this->createProjectFixture();
        $this->actingAs($fixture['account']);
        $fixture['requirement']->update(['blocked_reason' => null]);
        $this->travelTo('2026-08-18 10:01:00');
        $fixture['requirement']->complete();

        foreach ([false, true] as $is_draft) {
            if ($is_draft) {
                $this->travelTo('2026-08-18 10:02:00');
                $fixture['requirement']->update(['is_draft' => true]);
            }

            $data = $this->getJson('/exports/' . $fixture['project']->sqid . '/json')->assertOk()->json();
            $imported = Project::import($data)->requirements()->firstOrFail();
            $this->assertSame($is_draft, $imported->is_draft);
            $this->assertSame(!$is_draft, $imported->is_complete);
            $this->assertSame($fixture['requirement']->completed_at->toJSON(), $imported->completed_at->toJSON());
            $this->assertSame($fixture['requirement']->activity_at->toJSON(), $imported->activity_at->toJSON());
        }

        $this->travelBack();
    }
}

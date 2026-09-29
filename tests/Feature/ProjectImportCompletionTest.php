<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectImportCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_restores_requirement_completion_after_related_items_are_created(): void
    {
        $project = Project::import([
            'name' => 'Imported project',
            'actors' => [
                [
                    'id' => 1,
                    'name' => 'Users',
                ],
            ],
            'features' => [
                [
                    'name' => 'Feature',
                    'requirements' => [
                        [
                            'name' => 'ship the feature',
                            'activity_at' => '2026-08-18T10:00:00Z',
                            'completed_at' => '2026-08-18T10:01:00Z',
                            'tasks' => [
                                ['name' => 'Implement it', 'is_complete' => true],
                            ],
                            'unknowns' => [
                                ['name' => 'Does it need documentation?'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $requirement = $project->requirements()->firstOrFail();

        $this->assertSame('2026-08-18T10:00:00.000000Z', $requirement->activity_at->toJSON());
        $this->assertSame('2026-08-18T10:01:00.000000Z', $requirement->completed_at->toJSON());
        $this->assertTrue($requirement->is_complete);
    }

    public function test_import_rejects_completed_drafts_and_rolls_back_all_created_items(): void
    {
        foreach ([null, 'Waiting on access'] as $blocked_reason) {
            try {
                Project::import([
                    'name' => 'Invalid project',
                    'actors' => [['id' => 1, 'name' => 'Users']],
                    'features' => [[
                        'name' => 'Feature',
                        'requirements' => [
                            ['name' => 'valid requirement'],
                            [
                                'name' => 'contradictory requirement',
                                'is_draft' => true,
                                'blocked_reason' => $blocked_reason,
                                'activity_at' => '2026-08-18T10:00:00Z',
                                'completed_at' => '2026-08-18T10:01:00Z',
                                'actor_ids' => [1],
                                'tasks' => [['name' => 'Task', 'is_complete' => true]],
                                'unknowns' => [['name' => 'Question']],
                            ],
                        ],
                    ]],
                ]);
                $this->fail('The contradictory import should have failed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('features.0.requirements.1.is_draft', $exception->errors());
            }

            foreach (['projects', 'actors', 'features', 'requirements', 'assignments', 'tasks', 'unknowns'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        }
    }

    public function test_import_preserves_drafts_with_equal_or_stale_completion_timestamps(): void
    {
        foreach (['2026-08-18T09:59:00Z', '2026-08-18T10:00:00Z'] as $completed_at) {
            $project = Project::import([
                'name' => 'Draft project',
                'actors' => [['id' => 1, 'name' => 'Users']],
                'features' => [[
                    'name' => 'Feature',
                    'requirements' => [[
                        'name' => 'draft requirement',
                        'is_draft' => true,
                        'activity_at' => '2026-08-18T10:00:00Z',
                        'completed_at' => $completed_at,
                    ]],
                ]],
            ]);

            $requirement = $project->requirements()->firstOrFail();
            $this->assertTrue($requirement->is_draft);
            $this->assertFalse($requirement->is_complete);
            $this->assertSame($completed_at, $requirement->completed_at->format('Y-m-d\TH:i:s\Z'));
        }
    }
}

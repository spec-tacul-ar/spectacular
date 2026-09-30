<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectImportCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_ignores_requirement_activity_and_completion_timestamps(): void
    {
        $this->travelTo('2026-08-18 10:00:00');

        $project = Project::import([
            'name' => 'Imported project',
            'actors' => [['id' => 1, 'name' => 'Users']],
            'features' => [[
                'name' => 'Feature',
                'requirements' => [[
                    'name' => 'Requirement',
                    'activity_at' => '2026-08-17T10:00:00Z',
                    'completed_at' => '2026-08-17T10:01:00Z',
                ]],
            ]],
        ]);

        $requirement = $project->requirements()->firstOrFail();

        $this->assertSame('2026-08-18T10:00:00.000000Z', $requirement->activity_at->toJSON());
        $this->assertNull($requirement->completed_at);
        $this->assertFalse($requirement->is_complete);

        $this->travelBack();
    }
}

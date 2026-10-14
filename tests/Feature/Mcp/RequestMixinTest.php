<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\SpecificationsServer;
use App\Mcp\Tools\Features\FeatureEditTool;
use App\Models\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsApiFixtures;
use Tests\TestCase;

class RequestMixinTest extends TestCase
{
    use BuildsApiFixtures;
    use RefreshDatabase;

    public function test_ordinary_requests_are_not_mcp_requests(): void
    {
        $this->assertFalse(request()->isMcp());
        $this->assertFalse(Request::create('/api/projects')->isMcp());
    }

    public function test_http_mcp_calls_are_detected_only_while_the_method_is_running(): void
    {
        $fixture = $this->createProjectFixture();
        $this->actingAsAccount($fixture['account'], ['mcp:use']);
        $observed = [];
        Feature::updated(function () use (&$observed): void {
            $observed[] = request()->isMcp();
        });

        $this->postJson('/mcp/specifications', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'FeatureEditTool',
                'arguments' => ['id' => $fixture['feature']->sqid, 'data' => ['name' => 'Updated via MCP']],
            ],
        ])->assertOk()->assertJsonPath('result.structuredContent.name', 'Updated via MCP');

        $this->assertSame([true], $observed);
        $this->assertFalse(request()->isMcp());
        $feature = $fixture['feature']->fresh();
        $feature->loadHistory();
        $this->assertTrue($feature->history->last()['is_ai']);

        $feature->update(['name' => 'Ordinary update']);

        $this->assertSame([true, false], $observed);
        $this->assertFalse($feature->history->last()['is_ai']);
    }

    public function test_failed_mcp_writes_clear_detection_and_roll_back_the_transaction(): void
    {
        $fixture = $this->createProjectFixture();
        $feature = $fixture['feature'];
        $feature->loadHistory();
        $previous_history = $feature->history->all();
        Feature::updated(function (Feature $feature): void {
            if ($feature->name === 'Fail this write') {
                $this->assertTrue(request()->isMcp());

                throw ValidationException::withMessages(['name' => 'Write failed.']);
            }
        });

        SpecificationsServer::actingAs($fixture['account'])
            ->tool(FeatureEditTool::class, ['id' => $feature->sqid, 'data' => ['name' => 'Fail this write']])
            ->assertHasErrors(['Write failed.']);

        $this->assertFalse(request()->isMcp());
        $stored_feature = $feature->fresh();
        $stored_feature->loadHistory();
        $this->assertSame($feature->name, $stored_feature->name);
        $this->assertSame($previous_history, $stored_feature->history->all());

        $stored_feature->update(['name' => 'Ordinary update']);

        $this->assertFalse($stored_feature->history->last()['is_ai']);
    }
}

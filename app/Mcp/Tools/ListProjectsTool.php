<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('ListProjectsTool')]
#[Description('Lists the specifications available to the current account. Use it to identify the correct specification before calling GetProjectTool. IDs are opaque tool inputs; do not show them to users unless asked.')]
class ListProjectsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $projects = $request->user()->projects->map(fn($project) => [
            'id' => $project->sqid,
            'name' => $project->name,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ])->toArray();

        return Response::structured([
            'specifications' => $projects,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}

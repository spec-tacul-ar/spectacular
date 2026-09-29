<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Name('ListProjectAccountsTool')]
#[Description('Returns the names and IDs of accounts associated with a specification. Use it only when you need to attribute account_id values in item history to collaborators.')]
class ListProjectAccountsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
        ]);

        $project = (new Project())->resolveRouteBinding($validated['id']);

        if (!$project || !$request->user()->can('view', $project)) {
            return Response::error('Project not found.');
        }

        $accounts = $project->collaborators()
            ->orderBy('accounts.name')
            ->orderBy('accounts.id')
            ->get()
            ->map(fn($account) => [
                'name' => $account->name,
                'id' => $account->sqid,
            ])
            ->values();

        return Response::structured([
            'accounts' => $accounts,
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
            'id' => $schema->string()
                ->description('The specification ID returned by ListProjectsTool or GetProjectTool.')
                ->required(),
        ];
    }
}

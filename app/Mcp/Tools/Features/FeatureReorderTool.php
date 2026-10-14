<?php

namespace App\Mcp\Tools\Features;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Name('FeatureReorderTool')]
#[Description(
    'Sets the display order of all features in one project. '
    . 'Provide every current feature ID under that parent exactly once in the desired order. '
    . 'Missing, duplicate, foreign or deleted IDs are rejected before any write. '
    . 'Read the current siblings immediately before calling. '
    . 'Changes only ordering weights and preserves IDs, content and child items. '
    . 'Maximum 251 siblings (weights 0 through 250). '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class FeatureReorderTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'ids' => ['present', 'array', 'max:251'],
            'ids.*' => ['required', 'string', 'distinct:strict'],
        ]);
        try {
            $project = (new Project())->resolveRouteBinding($validated['project_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $project)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $ids = $validated['ids'];

        $result = DB::transaction(function () use ($project, $ids) {
            $items = $project->features()->lockForUpdate()->get()->keyBy('sqid');
            if (count($items) !== count($ids) || array_diff($ids, $items->keys()->all())) {
                throw ValidationException::withMessages(['ids' => 'Provide every current sibling ID exactly once.']);
            }
            foreach (array_values($ids) as $weight => $id) {
                $items[$id]->update(['weight' => $weight]);
            }

            return collect($ids)->map(fn($id) => ['id' => $id, 'weight' => $items[$id]->weight])->all();
        });

        return Response::structured(['items' => $result]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Opaque ID of the project whose features should be reordered.')->required(),
            'ids' => $schema->array()->items($schema->string())->unique()->max(251)->description('Every current sibling ID exactly once in desired display order. An empty array is valid only when there are no siblings.')->required(),
        ];
    }
}

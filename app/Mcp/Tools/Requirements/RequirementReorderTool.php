<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Feature;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
#[Name('requirement-reorder')]
#[Description(
    'Sets the display order of all requirements in one feature. '
    . 'Provide every current requirement ID under that parent exactly once in the desired order. '
    . 'Missing, duplicate, foreign or deleted IDs are rejected before any write. '
    . 'Read the current siblings immediately before calling. '
    . 'Changes only ordering weights and preserves IDs, content and child items. '
    . 'Maximum 251 siblings (weights 0 through 250). '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class RequirementReorderTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'feature_id' => ['required', 'string'],
            'ids' => ['present', 'array', 'max:251'],
            'ids.*' => ['required', 'string', 'distinct:strict'],
        ]);
        $feature_id = $request->decodeSqid('feature_id');
        $feature = $feature_id === null ? null : Feature::find($feature_id);
        if (!$feature) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $feature)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $ids = $validated['ids'];

        DB::transaction(function () use ($feature, $ids) {
            $items = $feature->requirements()->lockForUpdate()->get()->keyBy('sqid');
            if (count($items) !== count($ids) || array_diff($ids, $items->keys()->all())) {
                throw ValidationException::withMessages(['ids' => 'Provide every current sibling ID exactly once.']);
            }
            foreach (array_values($ids) as $weight => $id) {
                $items[$id]->update(['weight' => $weight]);
            }
        });

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feature_id' => $schema->string()->description('Opaque ID of the feature whose requirements should be reordered.')->required(),
            'ids' => $schema->array()->items($schema->string())->unique()->max(251)->description('Every current sibling ID exactly once in desired display order. An empty array is valid only when there are no siblings.')->required(),
        ];
    }
}

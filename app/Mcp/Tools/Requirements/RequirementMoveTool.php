<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Feature;
use App\Models\Requirement;
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
#[Name('requirement-move')]
#[Description(
    'Moves a requirement into another feature within the same active specification. '
    . 'Existing IDs, content and revisions are retained; a new revision records the parent change. '
    . 'The permanent reference and all assignments, tasks and unknowns move with it. '
    . 'It appends to destination display order; use requirement-reorder afterwards. '
    . 'Cross-specification moves are forbidden. '
    . 'Both source and destination require edit access. '
    . 'Moving changes activity on both parents and can invalidate completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class RequirementMoveTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'feature_id' => ['required', 'string'],
        ]);
        $requirement_id = $request->decodeSqid('id');
        $requirement = $requirement_id === null ? null : Requirement::find($requirement_id);
        if (!$requirement) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $feature_id = $request->decodeSqid('feature_id');
        $feature = $feature_id === null ? null : Feature::find($feature_id);
        if (!$feature) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $feature)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        if ($requirement->project->id !== $feature->project->id) {
            throw ValidationException::withMessages(['feature_id' => 'Items can only be moved within the same specification.']);
        }
        $old_feature = $requirement->feature;
        if (!$old_feature->is($feature)) {
            DB::transaction(function () use ($requirement, $feature, $old_feature) {
                $requirement->feature()->associate($feature);
                $requirement->weight = min(250, ($feature->requirements()->max('weight') ?? -1) + 1);
                $requirement->save();
                $old_feature->trackActivity();
            });
        }
        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current requirement to move.')->required(),
            'feature_id' => $schema->string()->description('Destination feature ID in the same specification.')->required(),
        ];
    }
}

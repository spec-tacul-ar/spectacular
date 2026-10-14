<?php

namespace App\Mcp\Tools\Unknowns;

use App\Models\Requirement;
use App\Models\Unknown;
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
#[Name('UnknownMoveTool')]
#[Description(
    'Moves a unknown into another requirement within the same active specification. '
    . 'Existing IDs, content and revisions are retained; a new revision records the parent change. '
    . 'Cross-specification moves are forbidden. '
    . 'Both source and destination require edit access. '
    . 'Moving changes activity on both parents and can invalidate completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class UnknownMoveTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'requirement_id' => ['required', 'string'],
        ]);
        try {
            $unknown = (new Unknown())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $unknown)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        try {
            $requirement = (new Requirement())->resolveRouteBinding($validated['requirement_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        if ($unknown->project->id !== $requirement->project->id) {
            throw ValidationException::withMessages(['requirement_id' => 'Items can only be moved within the same specification.']);
        }
        $old_requirement = $unknown->requirement;
        if (!$old_requirement->is($requirement)) {
            DB::transaction(function () use ($unknown, $requirement, $old_requirement) {
                $unknown->requirement()->associate($requirement);
                $unknown->save();
                $old_requirement->trackActivity();
            });
        }
        $unknown->refresh();

        $result = $unknown->obfuscateIdentifiers($unknown->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current unknown to move.')->required(),
            'requirement_id' => $schema->string()->description('Destination requirement ID in the same specification.')->required(),
        ];
    }
}

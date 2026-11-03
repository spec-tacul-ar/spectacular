<?php

namespace App\Mcp\Tools\Features;

use App\Actions\Features\DeleteFeature;

use App\Models\Feature;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
#[IsDestructive(true)]
#[IsOpenWorld(false)]
#[Name('feature-delete')]
#[Description(
    'Soft-deletes a current feature and records its removal while retaining prior revisions. '
    . 'Its requirements and their assignments, tasks and unknowns are also soft-deleted. '
    . 'Review the full affected scope before removing a feature. '
    . 'Comments and feedback are retained. '
    . 'No purge, restore, rollback or history edits are available. '
    . 'Returns success; live clients receive removal events. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class FeatureDeleteTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $feature = Feature::find($request->decodeSqid('id'));

        if (!$feature || !$request->user()->can('delete', $feature)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be deleted.']);
        }

        (new DeleteFeature())->handle($feature);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current feature to soft-delete.')->required(),
        ];
    }
}

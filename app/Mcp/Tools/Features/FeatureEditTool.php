<?php

namespace App\Mcp\Tools\Features;

use App\Actions\Features\EditFeature;
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
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Name('feature-edit')]
#[Description(
    'Edits a capability’s name or description. '
    . 'Read the current feature first and preserve established motivation, boundaries and exclusions. '
    . 'Requirements and their child items remain in place. '
    . 'Use requirement-move to move stories and requirement-reorder to arrange them. '
    . 'Supplying a description replaces its current content. '
    . 'Provide HTML for descriptions. '
    . 'Include existing content you want to retain. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class FeatureEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $feature = Feature::find($request->decodeSqid('id'));

        if (!$feature || !$request->user()->can('update', $feature)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $action = new EditFeature();
        $validated = $request->validate($action->rules());

        $action->handle($feature, $validated);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current feature from a read tool.')->required(),
            'name' => $schema->string()->description('A broad but focused product capability that groups related requirements, e.g. Orders, Stock or User Authentication; up to 250 characters.'),
            'description' => $schema->string()->nullable()->description('Motivation, capability boundaries, high-level context and explicit exclusions. Provide HTML, limited to 10000 characters. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
        ];
    }
}

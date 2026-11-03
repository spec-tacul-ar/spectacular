<?php

namespace App\Mcp\Tools\Features;

use App\Actions\Features\CreateFeature;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
#[Name('feature-create')]
#[Description(
    'Creates a product capability in a specification and appends it to feature display order. '
    . 'Choose a focused group such as Orders, Stock or User Authentication, broad enough for several related requirements. '
    . 'Explain motivation, boundaries and exclusions. '
    . 'Read the current specification to avoid duplicate capabilities. '
    . 'Use requirement-create to add its user stories. '
    . 'Provide descriptions as HTML, limited to 10000 characters. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class FeatureCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->merge(['project_id' => $request->decodeSqid('project_id')]);

        $action = new CreateFeature();
        $validated = $request->validate($action->rules());
        $feature = $action->handle($validated);

        return Response::structured(['success' => true, 'id' => $feature->sqid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Opaque ID of the parent project from a read tool; it must be in an active specification you can edit.')->required(),
            'name' => $schema->string()->description('A broad but focused product capability that groups related requirements, e.g. Orders, Stock or User Authentication; up to 250 characters.')->required(),
            'description' => $schema->string()->nullable()->description('Motivation, capability boundaries, high-level context and explicit exclusions. Provide HTML, limited to 10000 characters. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
        ];
    }
}

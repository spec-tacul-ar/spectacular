<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\EditProject;
use App\Models\Project;
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
#[Name('project-edit')]
#[Description(
    'Edits product context, name or language in a current specification. '
    . 'Read it first and preserve established scope. '
    . 'Only supplied fields change; roles, features, requirements and other children remain in place. '
    . 'Supplying a description replaces its current content. '
    . 'Provide HTML for descriptions. '
    . 'Include existing content you want to retain. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ProjectEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $project = Project::find($request->decodeSqid('id'));

        if (!$project || !$request->user()->can('update', $project)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $action = new EditProject();
        $validated = $request->validate($action->rules());

        $action->handle($project, $validated);

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current project from a read tool.')->required(),
            'name' => $schema->string()->description('A recognisable product name containing at least one letter; up to 250 characters.'),
            'description' => $schema->string()->nullable()->description('Purpose, product context, scope, constraints and exclusions. Provide HTML, limited to 10000 characters. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
            'locale' => $schema->string()->description('A supported specification language code, e.g. en. Defaults to en on creation.'),
        ];
    }
}

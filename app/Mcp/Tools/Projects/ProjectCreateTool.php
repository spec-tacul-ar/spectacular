<?php

namespace App\Mcp\Tools\Projects;

use App\Actions\Projects\CreateProject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
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
#[Name('project-create')]
#[Description(
    'Creates a specification with product context and returns its opaque ID. '
    . 'Read project-list first to avoid duplicates. '
    . 'Set a recognisable product name, purpose, scope, constraints and language. '
    . 'The authenticated account becomes its owner; no existing project is required. '
    . 'Next create product user roles with actor-create and capability groups with feature-create. '
    . 'Provide descriptions as HTML, limited to 10000 characters. '
    . 'Any authenticated account can create a specification; subsequent edits require owner/editor access. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ProjectCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $action = new CreateProject();
        $validated = $request->validate($action->rules());
        $project = DB::transaction(fn() => $action->handle($request->user(), $validated));

        return Response::structured(['success' => true, 'id' => $project->sqid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('A recognisable product name containing at least one letter; up to 250 characters.')->required(),
            'description' => $schema->string()->nullable()->description('Purpose, product context, scope, constraints and exclusions. Provide HTML, limited to 10000 characters. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
            'locale' => $schema->string()->description('A supported specification language code, e.g. en. Defaults to en on creation.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Projects;

use App\Enums\Role;
use App\Models\Project;
use App\Rules\SluggableName;
use App\Rules\SupportedLocale;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
#[Name('ProjectCreateTool')]
#[Description(
    'Creates a specification with product context and returns its current state and opaque ID. '
    . 'Read ProjectListTool first to avoid duplicates. '
    . 'Set a recognisable product name, purpose, scope, constraints and language. '
    . 'The authenticated account becomes its owner; no existing project is required. '
    . 'Next create product user roles with ActorCreateTool and capability groups with FeatureCreateTool. '
    . 'Provide descriptions as Markdown; the server converts them to stored HTML, limited to 10000 characters. '
    . 'Any authenticated account can create a specification; subsequent edits require owner/editor access. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ProjectCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'data' => ['required', 'array:name,description,locale'],
            'data.name' => ['required', 'string', 'max:250', new SluggableName()],
            'data.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'data.locale' => ['sometimes', 'required', 'string', new SupportedLocale()],
        ]);
        $data = $validated['data'];
        if (isset($data['description'])) {
            $data['description'] = Str::markdown($data['description'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            Validator::make($data, ['description' => ['max:10000']])->validate();
        }

        if (!$request->user()->can('create', Project::class)) {
            return Response::error('A specification cannot be created.');
        }

        $project = DB::transaction(function () use ($data, $request) {
            $project = Project::create($data);
            $project->addCollaboration($request->user(), Role::OWNER);

            return $project->refresh();
        });

        $result = $project->obfuscateIdentifiers($project->attributesToArray());
        unset($result['history'], $result['uuid']);
        $result['description'] = Str::htmlToMarkdown($result['description'] ?? '');

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->object([
                'name' => $schema->string()->description('A recognisable product name containing at least one letter; up to 250 characters.')->required(),
                'description' => $schema->string()->nullable()->description('Purpose, product context, scope, constraints and exclusions. Provide Markdown; the server converts it to stored HTML, limited to 10000 characters. Raw HTML is stripped. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
                'locale' => $schema->string()->description('A supported specification language code, e.g. en. Defaults to en on creation.'),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new project only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

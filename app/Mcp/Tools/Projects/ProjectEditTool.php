<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use App\Rules\SluggableName;
use App\Rules\SupportedLocale;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
#[Name('ProjectEditTool')]
#[Description(
    'Edits product context, name or language in a current specification. '
    . 'Read it first and preserve established scope. '
    . 'Only supplied fields change; roles, features, requirements and other children remain in place. '
    . 'Supplying a description replaces its current content. '
    . 'Provide Markdown; the server converts it to stored HTML. '
    . 'Include existing content you want to retain. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ProjectEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'data' => ['required', 'array:name,description,locale', 'min:1'],
            'data.name' => ['required', 'sometimes', 'string', 'max:250', new SluggableName()],
            'data.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'data.locale' => ['sometimes', 'required', 'string', new SupportedLocale()],
        ]);
        try {
            $project = (new Project())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $project)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        $data = $validated['data'];
        if (isset($data['description'])) {
            $data['description'] = Str::markdown($data['description'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            Validator::make($data, ['description' => ['max:10000']])->validate();
        }

        DB::transaction(fn() => $project->update($data));
        $project->refresh();

        $result = $project->obfuscateIdentifiers($project->attributesToArray());
        unset($result['history'], $result['uuid']);
        $result['description'] = Str::htmlToMarkdown($result['description'] ?? '');

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current project from a read or create tool.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A recognisable product name containing at least one letter; up to 250 characters.'),
                'description' => $schema->string()->nullable()->description('Purpose, product context, scope, constraints and exclusions. Provide Markdown; the server converts it to stored HTML, limited to 10000 characters. Raw HTML is stripped. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
                'locale' => $schema->string()->description('A supported specification language code, e.g. en. Defaults to en on creation.'),
            ])->withoutAdditionalProperties()->required()->description('Non-empty partial update. Omitted fields and all child items remain unchanged; null clears nullable fields.'),
        ];
    }
}

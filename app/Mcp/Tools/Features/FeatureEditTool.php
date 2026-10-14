<?php

namespace App\Mcp\Tools\Features;

use App\Models\Feature;
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
#[Name('FeatureEditTool')]
#[Description(
    'Edits a capability’s name or description. '
    . 'Read the current feature first and preserve established motivation, boundaries and exclusions. '
    . 'Requirements and their child items remain in place. '
    . 'Use RequirementMoveTool to move stories and RequirementReorderTool to arrange them. '
    . 'Supplying a description replaces its current content. '
    . 'Provide Markdown; the server converts it to stored HTML. '
    . 'Include existing content you want to retain. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class FeatureEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'data' => ['required', 'array:name,description', 'min:1'],
            'data.name' => ['required', 'sometimes', 'string', 'max:250'],
            'data.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);
        try {
            $feature = (new Feature())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $feature)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        $data = $validated['data'];
        if (isset($data['description'])) {
            $data['description'] = Str::markdown($data['description'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            Validator::make($data, ['description' => ['max:10000']])->validate();
        }

        DB::transaction(fn() => $feature->update($data));
        $feature->refresh();

        $result = $feature->obfuscateIdentifiers($feature->attributesToArray());
        unset($result['history'], $result['uuid']);
        $result['description'] = Str::htmlToMarkdown($result['description'] ?? '');

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current feature from a read or create tool.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A broad but focused product capability that groups related requirements, e.g. Orders, Stock or User Authentication; up to 250 characters.'),
                'description' => $schema->string()->nullable()->description('Motivation, capability boundaries, high-level context and explicit exclusions. Provide Markdown; the server converts it to stored HTML, limited to 10000 characters. Raw HTML is stripped. On edits this replaces the entire description; include existing content you want to retain. Null clears it.'),
            ])->withoutAdditionalProperties()->required()->description('Non-empty partial update. Omitted fields and all child items remain unchanged; null clears nullable fields.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Unknowns;

use App\Models\Unknown;
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
#[IsDestructive(true)]
#[IsOpenWorld(false)]
#[Name('UnknownResolveTool')]
#[Description(
    'Resolves one unanswered product question by appending an established clarification to its requirement description and soft-deleting the unknown, atomically. '
    . 'Read the parent requirement and obtain the answer from the user or established evidence first; never invent an answer. '
    . 'The existing description is retained. '
    . 'Provide the clarification as Markdown; the server converts it to HTML before appending it to the stored description. The combined HTML must fit within the 10000-character description limit. '
    . 'The question and revisions remain in history; comments and feedback are untouched. '
    . 'Requires owner/editor access to an active specification. '
    . 'This changes requirement activity and can invalidate completion. '
    . 'Returns the updated requirement and removed unknown ID.',
)]
class UnknownResolveTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'clarification' => ['required', 'string', 'max:10000'],
        ]);
        try {
            $unknown = (new Unknown())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $unknown)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        $requirement = $unknown->requirement;
        $html = Str::markdown($validated['clarification'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $description = ($requirement->description ?? '') . $html;
        Validator::make(['description' => $description], ['description' => ['max:10000']])->validate();

        DB::transaction(function () use ($requirement, $unknown, $description) {
            $requirement->update(['description' => $description]);
            $unknown->delete();
        });

        $requirement->refresh();
        $result = $requirement->obfuscateIdentifiers($requirement->attributesToArray());
        unset($result['history'], $result['uuid']);
        $result['description'] = Str::htmlToMarkdown($result['description'] ?? '');

        return Response::structured([
            'requirement' => [...$result, 'is_complete' => $requirement->is_complete],
            'unknown' => ['id' => $unknown->sqid, 'deleted_at' => $unknown->deleted_at],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the unresolved unknown, obtained from a read tool.')->required(),
            'clarification' => $schema->string()->description('The established answer in Markdown, phrased as requirement behaviour or acceptance criteria. Converted to HTML and appended to the existing stored description; the combined HTML must not exceed 10000 characters.')->required(),
        ];
    }
}

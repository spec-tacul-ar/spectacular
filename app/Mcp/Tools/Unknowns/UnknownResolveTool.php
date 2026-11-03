<?php

namespace App\Mcp\Tools\Unknowns;

use App\Models\Unknown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
#[Name('unknown-resolve')]
#[Description(
    'Resolves one unanswered product question by appending an established clarification to its requirement description and soft-deleting the unknown, atomically. '
    . 'Read the parent requirement and obtain the answer from the user or established evidence first; never invent an answer. '
    . 'The existing description is retained. '
    . 'Provide the clarification as HTML; it is appended to the stored description. The combined HTML must fit within the 10000-character description limit. '
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
        $unknown_id = $request->decodeSqid('id');
        $unknown = $unknown_id === null ? null : Unknown::find($unknown_id);
        if (!$unknown) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $unknown)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        $html = $validated['clarification'];

        DB::transaction(function () use ($request, $unknown, $html) {
            // Lock the parent first, then recheck the question: it may have moved or
            // been removed while this request was waiting for another writer.
            $requirement = $unknown->requirement()->lockForUpdate()->first();
            $unknown = $requirement?->unknowns()->lockForUpdate()->find($unknown->id);

            if (!$unknown || !$request->user()->can('update', $requirement)) {
                throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
            }

            $unknown->setRelation('requirement', $requirement);
            $description = ($requirement->description ?? '') . $html;
            Validator::make(['description' => $description], ['description' => ['max:10000']])->validate();

            $requirement->update(['description' => $description]);
            $unknown->delete();

        });

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the unresolved unknown, obtained from a read tool.')->required(),
            'clarification' => $schema->string()->description('The established answer in HTML, phrased as requirement behaviour or acceptance criteria. Appended to the existing stored description; the combined HTML must not exceed 10000 characters.')->required(),
        ];
    }
}

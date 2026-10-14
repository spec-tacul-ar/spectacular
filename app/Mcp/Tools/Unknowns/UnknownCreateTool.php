<?php

namespace App\Mcp\Tools\Unknowns;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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
#[Name('UnknownCreateTool')]
#[Description(
    'Creates a precise unanswered product question under a requirement. '
    . 'Read the parent requirement first and avoid duplicate questions. '
    . 'Unknowns document ambiguities to resolve before implementation; use blocked_reason on the requirement for concrete delivery obstacles. '
    . 'Do not invent answers. '
    . 'Use UnknownResolveTool when the user or established evidence provides a clarification. '
    . 'Adding an unknown changes parent activity and can invalidate completion. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class UnknownCreateTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'requirement_id' => ['required', 'string'],
            'data' => ['required', 'array:name'],
            'data.name' => ['required', 'string', 'max:250'],
        ]);
        $data = $validated['data'];
        try {
            $requirement = (new Requirement())->resolveRouteBinding($validated['requirement_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $requirement)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        $unknown = DB::transaction(fn() => $requirement->unknowns()->create($data)->refresh());

        $result = $unknown->obfuscateIdentifiers($unknown->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->string()->description('Opaque ID of the parent requirement from a read tool; it must be in an active specification you can edit.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A precise unanswered product question, e.g. Can an order include multiple tables?; up to 250 characters. Unknowns capture ambiguities, whereas blockers record concrete delivery obstacles.')->required(),
            ])->withoutAdditionalProperties()->required()->description('Fields for the new unknown only. Server-generated IDs, timestamps, history and nested items cannot be supplied.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools\Actors;

use App\Models\Actor;
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
#[Name('ActorEditTool')]
#[Description(
    'Edits a product user role’s plural name or plain-text summary of duties and motivation. '
    . 'Read the current role first. '
    . 'Existing requirement assignments are preserved and their generated titles reflect the role name. '
    . 'This does not edit an account, collaborator or persona. '
    . 'Omitted fields stay unchanged; null clears nullable fields. '
    . 'Only owners/editors may write to active specifications. '
    . 'Writes append revisions and cannot change comments, feedback, accounts, collaborators, timestamps or history.',
)]
class ActorEditTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'data' => ['required', 'array:name,summary', 'min:1'],
            'data.name' => ['required', 'sometimes', 'string', 'max:250'],
            'data.summary' => ['sometimes', 'nullable', 'string', 'max:2500'],
        ]);
        try {
            $actor = (new Actor())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }

        if (!$request->user()->can('update', $actor)) {
            throw ValidationException::withMessages(['id' => 'Item not found or cannot be edited.']);
        }
        $data = $validated['data'];

        DB::transaction(fn() => $actor->update($data));
        $actor->refresh();

        $result = $actor->obfuscateIdentifiers($actor->attributesToArray());
        unset($result['history'], $result['uuid']);

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Opaque ID of the current actor from a read or create tool.')->required(),
            'data' => $schema->object([
                'name' => $schema->string()->description('A succinct plural product role, e.g. Waiters, Managers, AI Agents or API Clients; up to 250 characters. Avoid individual personas or collaborator names.'),
                'summary' => $schema->string()->nullable()->description('Plain text describing this role’s duties, interactions and motivation; up to 2500 characters. Null clears it.'),
            ])->withoutAdditionalProperties()->required()->description('Non-empty partial update. Omitted fields and all child items remain unchanged; null clears nullable fields.'),
        ];
    }
}

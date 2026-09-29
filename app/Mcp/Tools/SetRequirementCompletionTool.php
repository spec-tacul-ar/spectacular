<?php

namespace App\Mcp\Tools;

use App\Models\Requirement;
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
#[Name('SetRequirementCompletionTool')]
#[Description(
    'Marks an unblocked requirement complete or reopens it. Call this only after checking the current requirement and only when the user-authorized work has been implemented and verified. '
    . 'Do not use it to advance a draft requirement: completing one clears its draft status. Task completion alone does not establish requirement completion.',
)]
class SetRequirementCompletionTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'is_complete' => ['required', 'boolean'],
        ]);

        $requirement = (new Requirement())->resolveRouteBinding($validated['id']);

        if (! $requirement || ! $request->user()->can('update', $requirement)) {
            return Response::error('Requirement not found or cannot be edited.');
        }

        if ($validated['is_complete']) {
            if ($requirement->is_blocked) {
                throw ValidationException::withMessages([
                    'id' => 'Requirements cannot be completed while blocked.',
                ]);
            }

            $requirement->complete();
        } else {
            $requirement->reopen();
        }

        return Response::structured([
            'id' => $requirement->sqid,
            'activity_at' => $requirement->activity_at,
            'completed_at' => $requirement->completed_at,
            'is_complete' => $requirement->is_complete,
            'is_draft' => $requirement->is_draft,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The requirement ID returned by GetProjectTool, GetItemTool, or GetChangesTool.')
                ->required(),
            'is_complete' => $schema->boolean()
                ->description('True to mark a verified, non-draft requirement complete; false to reopen a requirement.')
                ->required(),
        ];
    }
}

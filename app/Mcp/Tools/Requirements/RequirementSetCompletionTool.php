<?php

namespace App\Mcp\Tools\Requirements;

use App\Actions\Requirements\CompleteRequirement;
use App\Actions\Requirements\ReopenRequirement;

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
#[Name('requirement-set-completion')]
#[Description(
    'Marks an unblocked requirement complete or reopens it. Call this only after checking the current requirement and only when the user-authorized work has been implemented and verified. '
    . 'Do not use it to advance a draft requirement: completing one clears its draft status. Task completion alone does not establish requirement completion.',
)]
class RequirementSetCompletionTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'is_complete' => ['required', 'boolean'],
        ]);

        try {
            $requirement = (new Requirement())->resolveRouteBinding($validated['id']);
        } catch (ModelNotFoundException) {
            return Response::error('Requirement not found or cannot be edited.');
        }

        if (!$request->user()->can('update', $requirement)) {
            return Response::error('Requirement not found or cannot be edited.');
        }

        DB::transaction(fn() => $validated['is_complete']
            ? (new CompleteRequirement())->handle($requirement)
            : (new ReopenRequirement())->handle($requirement));

        return Response::structured(['success' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The requirement ID returned by project-get, requirement-get, or project-get-changes.')
                ->required(),
            'is_complete' => $schema->boolean()
                ->description('True to mark a verified, non-draft requirement complete; false to reopen a requirement.')
                ->required(),
        ];
    }
}

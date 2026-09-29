<?php

namespace App\Actions\Requirements;

use App\Http\Resources\RequirementResource;
use App\Models\Requirement;
use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SetRequirementDraft
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('requirement'));
    }

    public static function routes(Router $router): void
    {
        $router->post('requirements/{requirement}/draft', static::class);
    }

    public function rules(): array
    {
        return ['is_draft' => ['required', 'boolean']];
    }

    public function handle(Requirement $requirement, bool $is_draft): Requirement
    {
        if ($is_draft && $requirement->is_complete) {
            throw ValidationException::withMessages([
                'is_draft' => 'Completed requirements cannot be marked as draft.',
            ]);
        }

        $requirement->update(['is_draft' => $is_draft]);

        return $requirement;
    }

    public function asController(ActionRequest $request, Requirement $requirement): RequirementResource
    {
        return new RequirementResource($this->handle($requirement, $request->boolean('is_draft')));
    }
}

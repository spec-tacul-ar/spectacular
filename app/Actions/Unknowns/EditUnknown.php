<?php

namespace App\Actions\Unknowns;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\UnknownResource;
use App\Models\Unknown;

class EditUnknown
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('unknown'));
    }

    public static function routes(Router $router): void
    {
        $router->post('unknowns/{unknown}/edit', static::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:250'],
        ];
    }

    public function handle(Unknown $unknown, array $validated): Unknown
    {
        $unknown->update($validated);

        return $unknown;
    }

    public function asController(ActionRequest $request, Unknown $unknown): UnknownResource
    {
        $unknown = $this->handle($unknown, $request->validated());

        return new UnknownResource($unknown);
    }
}

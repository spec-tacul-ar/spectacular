<?php

namespace App\Actions\Features;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\FeatureResource;
use App\Models\Feature;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EditFeature
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('feature'));
    }

    public static function routes(Router $router): void
    {
        $router->post('features/{feature}/edit', static::class)
            ->middleware('sqids:project_id');
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'name' => ['sometimes', 'required', 'string', 'max:250'],
            'weight' => ['sometimes', 'nullable', 'integer', 'between:0,250'],
        ];
    }

    public function handle(Feature $feature, array $validated): Feature
    {
        return DB::transaction(function () use ($feature, $validated) {
            $feature->update($validated);

            return $feature;
        });
    }

    public function asController(ActionRequest $request, Feature $feature): FeatureResource
    {
        $feature = $this->handle($feature, $request->validated());

        return new FeatureResource($feature);
    }
}

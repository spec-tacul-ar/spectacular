<?php

namespace App\Actions\Features;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\FeatureResource;
use App\Models\Feature;
use App\Models\Project;
use App\Rules\Authorised;

class CreateFeature
{
    use AsAction;

    public static function routes(Router $router): void
    {
        $router->post('features', static::class)
            ->middleware('sqids:project_id');
    }

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('create', Feature::class);
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:10000'],
            'name' => ['required', 'string', 'max:250'],
            'project_id' => ['required', 'integer', new Authorised('update', Project::class)],
        ];
    }

    public function handle(array $data): Feature
    {
        $project = Project::withMax('features', 'weight')->findOrFail($data['project_id']);

        $weight = $project->features_max_weight === null ? 0 : $project->features_max_weight + 1;

        $data['weight'] = min($weight, 250);

        return Feature::create($data);
    }

    public function asController(ActionRequest $request): FeatureResource
    {
        $feature = $this->handle($request->validated());

        return new FeatureResource($feature);
    }
}

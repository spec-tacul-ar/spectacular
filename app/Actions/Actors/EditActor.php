<?php

namespace App\Actions\Actors;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Router;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\ActorResource;
use App\Models\Actor;

class EditActor
{
    use AsAction;

    public function authorize(ActionRequest $request): GateResponse
    {
        return Gate::inspect('update', $request->route('actor'));
    }

    public static function routes(Router $router): void
    {
        $router->post('actors/{actor}/edit', static::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:250'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2500'],
            'weight' => ['sometimes', 'nullable', 'integer', 'between:0,250'],
        ];
    }

    public function handle(Actor $actor, array $validated): Actor
    {
        $actor->update($validated);

        return $actor;
    }

    public function asController(ActionRequest $request, Actor $actor): ActorResource
    {
        $actor = $this->handle($actor, $request->validated());

        return new ActorResource($actor);
    }
}

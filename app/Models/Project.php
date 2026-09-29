<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Project extends Model
{
    use HasFactory;
    use HasRelationships;
    use Traits\BroadcastsActivity;
    use Traits\HasSqid;
    use Traits\Revisionable;
    use Traits\TracksActivity;

    protected $fillable = [
        'description',
        'locale',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn($requirement) => $requirement->trackActivity());

        static::deleting(function ($project) {
            $project->comments->each->delete();
            $project->collaborations->each->delete();
            $project->features()->withTrashed()->get()->each->forceDelete();
            $project->invitations->each->delete();
            $project->actors()->withTrashed()->get()->each->forceDelete();
        });
    }

    /* Relations */

    public function collaborators()
    {
        return $this->belongsToMany(Account::class, 'collaborations')
            ->withPivot('role', 'read_at')
            ->withTimestamps();
    }

    public function actors(): HasMany
    {
        return $this->hasMany(Actor::class);
    }

    public function assignments()
    {
        return $this->hasManyThrough(Assignment::class, Actor::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function collaborations()
    {
        return $this->hasMany(Collaboration::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class)->chaperone();
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function requirements(): HasManyThrough
    {
        return $this->hasManyThrough(Requirement::class, Feature::class);
    }

    public function unknowns(): HasManyDeep
    {
        return $this->hasManyDeep(Unknown::class, [Feature::class, Requirement::class]);
    }

    public function tasks(): HasManyDeep
    {
        return $this->hasManyDeep(Task::class, [Feature::class, Requirement::class]);
    }

    /* Scopes */

    public function scopeOwnedBy($query, Account $account)
    {
        return $query->whereHas('collaborations', function ($query) use ($account) {
            return $query->whereBelongsTo($account)->where('role', Role::OWNER);
        });
    }

    public function scopeArchived($query, bool $archived = true)
    {
        return $archived
            ? $query->whereNotNull('archived_at')
            : $query->whereNull('archived_at');
    }

    /* Helpers */

    public function addCollaboration(Account $account, Role $role): static
    {
        $this->collaborations()->make(['role' => $role])->account()->associate($account)->save();

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): static
    {
        if (!$this->isArchived()) {
            $this->archived_at = now();
            $this->save();
        }

        return $this;
    }

    public function restore(): static
    {
        $this->archived_at = null;
        $this->save();

        return $this;
    }

    public static function import(array $data): static
    {
        // Note: This is only good for importing the demo project at the moment.

        $data = Validator::make($data, [
            'id' => ['nullable', 'uuid'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'actors' => ['required', 'array'],
            'actors.*.id' => ['required'],
            'actors.*.name' => ['required', 'string'],
            'actors.*.summary' => ['nullable', 'string'],
            'actors.*.weight' => ['nullable', 'integer'],
            'features' => ['required', 'array'],
            'features.*.name' => ['required', 'string'],
            'features.*.description' => ['nullable', 'string'],
            'features.*.weight' => ['nullable', 'integer'],
            'features.*.requirements' => ['required', 'array'],
            'features.*.requirements.*.name' => ['nullable', 'string'],
            'features.*.requirements.*.description' => ['nullable', 'string'],
            'features.*.requirements.*.blocked_reason' => ['nullable', 'string'],
            'features.*.requirements.*.activity_at' => ['nullable', 'date'],
            'features.*.requirements.*.completed_at' => ['nullable', 'date'],
            'features.*.requirements.*.is_draft' => ['sometimes', 'boolean'],
            'features.*.requirements.*.source' => ['nullable', 'string'],
            'features.*.requirements.*.reference' => ['nullable'],
            'features.*.requirements.*.weight' => ['nullable', 'integer'],
            'features.*.requirements.*.tasks' => ['sometimes', 'array'],
            'features.*.requirements.*.tasks.*.name' => ['required', 'string'],
            'features.*.requirements.*.tasks.*.is_complete' => ['boolean'],
            'features.*.requirements.*.tasks.*.weight' => ['nullable', 'integer'],
            'features.*.requirements.*.unknowns' => ['sometimes', 'array'],
            'features.*.requirements.*.unknowns.*.name' => ['required', 'string'],
            'features.*.requirements.*.actor_ids' => ['sometimes', 'array'],
        ])->validate();

        return DB::transaction(function () use ($data) {
            $project = new Project([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $project->save();

            $actors = collect($data['actors'])
                ->keyBy('id')
                ->map(fn($actor) => $project->actors()->create([
                    'name' => $actor['name'],
                    'summary' => $actor['summary'] ?? null,
                    'weight' => $actor['weight'] ?? null,
                ]));

            foreach ($data['features'] as $feature_index => $feature_data) {
                $feature_model = $project->features()->create([
                    'name' => $feature_data['name'],
                    'description' => $feature_data['description'] ?? null,
                    'weight' => $feature_data['weight'] ?? null,
                ]);

                foreach ($feature_data['requirements'] as $requirement_index => $requirement_data) {
                    $requirement_model = $feature_model->requirements()->create([
                        'name' => $requirement_data['name'] ?? null,
                        'description' => $requirement_data['description'] ?? null,
                        'blocked_reason' => $requirement_data['blocked_reason'] ?? null,
                        'is_draft' => $requirement_data['is_draft'] ?? false,
                        'source' => $requirement_data['source'] ?? null,
                        'reference' => $requirement_data['reference'] ?? null,
                        'weight' => $requirement_data['weight'] ?? null,
                    ]);

                    foreach ($requirement_data['tasks'] ?? [] as $task_data) {
                        $requirement_model->tasks()->create([
                            'name' => $task_data['name'],
                            'is_complete' => $task_data['is_complete'] ?? false,
                            'weight' => $task_data['weight'] ?? null,
                        ]);
                    }

                    foreach ($requirement_data['unknowns'] ?? [] as $unknown_data) {
                        $requirement_model->unknowns()->create([
                            'name' => $unknown_data['name'],
                        ]);
                    }

                    foreach ($requirement_data['actor_ids'] ?? [] as $actor_id) {
                        $requirement_model->assignments()->create([
                            'actor_id' => $actors[$actor_id]->id,
                        ]);
                    }

                    if (array_key_exists('completed_at', $requirement_data) || array_key_exists('activity_at', $requirement_data)) {
                        $requirement_model->forceFill([
                            'activity_at' => $requirement_data['activity_at'] ?? $requirement_model->activity_at,
                            'completed_at' => $requirement_data['completed_at'] ?? null,
                        ]);

                        if ($requirement_model->is_draft && $requirement_model->is_complete) {
                            throw ValidationException::withMessages([
                                'features.' . $feature_index . '.requirements.' . $requirement_index . '.is_draft' => 'Completed requirements cannot be marked as draft.',
                            ]);
                        }

                        $requirement_model->forceFill([
                            'completed_at' => $requirement_model->is_blocked
                                ? null
                                : ($requirement_data['completed_at'] ?? null),
                        ])->saveQuietly();
                    }
                }
            }

            return $project;
        });
    }

    /* Attributes */

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn() => str($this->name)->slug()->toString() ?: 'project',
        );
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn() => url('app/projects/' . $this->getRouteKey()),
        );
    }
}

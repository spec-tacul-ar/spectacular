<?php

namespace App\Models;

use App\Casts\AsSqid;
use App\Models\Scopes\WeightedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Feature extends Model
{
    use HasFactory;
    use Traits\BroadcastsActivity;
    use Traits\HasSqid;
    use Traits\Revisionable;
    use Traits\TracksActivity;

    protected $casts = [
        'is_percentage' => 'boolean',
        'project_sqid' => AsSqid::class,
    ];

    protected $fillable = [
        'description',
        'name',
        'project_id',
        'weight',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WeightedScope());

        static::saved(fn($feature) => $feature->trackActivity());
        static::deleted(function ($feature) {
            if (! $feature->isForceDeleting()) {
                $feature->trackActivity();
            }
        });

        static::deleting(function ($feature) {
            if (!request()->isMcp() || $feature->isForceDeleting()) {
                $feature->comments()->delete();
            }

            $feature->requirements->each->delete();
        });

        static::forceDeleting(function ($feature) {
            $feature->comments()->delete();
            $feature->requirements()->withTrashed()->get()->each->forceDelete();
        });
    }

    protected function handleActivity()
    {
        $this->project->trackActivity();
    }

    /* Relations */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class)->chaperone();
    }

}

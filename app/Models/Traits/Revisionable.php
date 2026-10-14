<?php

namespace App\Models\Traits;

use App\Casts\CompressedCollection;
use App\Models\Scopes\WithoutHistoryScope;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

trait Revisionable
{
    use HasUuids;
    use SoftDeletes;

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected function initializeRevisionable(): void
    {
        $this->attributes['history'] = null;

        $this->mergeCasts([
            'history' => CompressedCollection::class,
        ]);
    }

    public static function bootRevisionable()
    {
        static::addGlobalScope(app(WithoutHistoryScope::class));

        static::updated(function ($model) {
            $data = array_intersect_key($model->getOriginal(), $model->getChanges());

            $model->saveRevision($data);
        });

        // Laravel thinks we're calling the method trashed() if we don't use registerModelEvent()
        static::registerModelEvent('trashed', fn($model) => $model->saveRevision(['deleted_at' => null]));
    }

    /* Scopes */

    public function scopeWithHistory($query)
    {
        return $query->withoutGlobalScope(WithoutHistoryScope::class);
    }

    /* Helpers */

    public function saveRevision($data)
    {
        $this->loadHistory();

        $this->history->push([
            'timestamp' => now()->toISOString(),
            'account_id' => auth()->user()?->sqid,
            'is_ai' => request()->isMcp(),
            'data' => $data,
        ]);

        $this->saveQuietly();

        return $this;
    }

    public function rollBack(DateTime $timestamp, $with = [])
    {
        if ($timestamp < $this->created_at) {
            throw new LogicException('Cannot roll back before model was created.');
        }

        $this->loadHistory();

        $this->history
            ->where('timestamp', '>', $timestamp->toISOString())
            ->sortByDesc('timestamp')
            ->each(fn($revision) => $this->forceFill($revision['data']));

        foreach ($with as $relation) {
            $this->load([
                $relation => function ($query) use ($timestamp) {
                    if ($query instanceof HasOneOrManyThrough) {
                        $query->withTrashedParents();
                    }

                    return $query
                        ->withHistory()
                        ->withTrashed()
                        ->afterQuery(fn($models) => $models
                            ->where('created_at', '<', $timestamp->toDateTimeString())
                            ->each->rollBack($timestamp)
                            ->whereNull('deleted_at'));
                },
            ]);
        }
    }

    /**
     * Returns the fields that have changed since a given timestamp and what their values are now.
     */
    public function getChangesSince(DateTime $timestamp)
    {
        $this->loadHistory();

        if ($this->deleted_at) {
            return [
                'id' => $this->sqid,
                'deleted_at' => $this->deleted_at,
            ];
        }

        if ($this->created_at->isAfter($timestamp)) {
            return $this->obfuscateIdentifiers($this->toArray());
        }

        $fields = $this->history
            ->where('timestamp', '>', $timestamp->toISOString())
            ->map(fn($item) => array_keys($item['data']))
            ->flatten()
            ->unique()
            ->toArray();

        return $this->obfuscateIdentifiers($this->only($fields));
    }

    /**
     * Load the history in instance where the WithoutHistoryScope global scope blocked it.
     */
    public function loadHistory(): void
    {
        if (! $this->exists || $this->getRawOriginal('history') !== null) {
            return;
        }

        $this->attributes['history'] = $this->newQuery()
            ->withTrashed()
            ->withHistory()
            ->whereKey($this->getKey())
            ->toBase()
            ->value('history');
    }
}

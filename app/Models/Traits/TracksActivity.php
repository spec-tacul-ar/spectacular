<?php

namespace App\Models\Traits;

use App\Events\ProjectItemSaved;

trait TracksActivity
{
    protected function initializeTracksActivity(): void
    {
        $this->mergeCasts([
            'activity_at' => 'datetime',
        ]);
    }

    public function trackActivity()
    {
        self::withoutTimestamps(function () {
            $this->forceFill([
                'activity_at' => now(),
            ])->saveQuietly();
        });

        if (!$this->trashed() && $this->wasChanged('activity_at')) {
            broadcast(new ProjectItemSaved($this))->toOthers();
        }

        $this->handleActivity();
    }

    protected function handleActivity()
    {
        // Override this to call trackActivity on parent(s)
    }
}

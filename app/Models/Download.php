<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Download extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        // Listen for the updating event and update the updated_at timestamp
        static::updating(function ($download) {
            $download->updated_at = now();
        });
    }

    public function links(): HasMany {
        return $this->hasMany(DownloadLink::class);
    }

    /**
     * Calculate the progress of the download.
     */
    public function getProgress(): int {
        $totalChunks = $this->links->flatMap(function ($link) {
            return $link->chunks ?? [];
        })->count();

        $completedChunks = $this->links->flatMap(function ($link) {
            return $link->chunks->where('completed', true) ?? [];
        })->count();

        if ($totalChunks === 0) {
            return 0; // Avoid division by zero
        }

        $progressPercentage = ($completedChunks / $totalChunks) * 100;

        return $progressPercentage;
    }

}

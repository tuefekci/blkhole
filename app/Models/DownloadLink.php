<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DownloadLink extends Model
{
    use HasFactory;
    protected $guarded = [];  

    public function download(): BelongsTo
    {
        return $this->belongsTo(Download::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DownloadLinkFile::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DownloadLinkChunk::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DownloadLinkChunk extends Model
{
    use HasFactory;
    protected $guarded = [];  

    public function link(): BelongsTo
    {
        return $this->belongsTo(DownloadLink::class);
    }

    public function download()
    {
        return $this->link()->download();
    }


}

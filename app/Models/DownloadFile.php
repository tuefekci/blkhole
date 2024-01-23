<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadFile extends Model
{
    use HasFactory;

    public function download()
    {
        return $this->belongsTo(Download::class);
    }

    public function chunks()
    {
        return $this->hasMany(DownloadFileChunk::class);
    }
}

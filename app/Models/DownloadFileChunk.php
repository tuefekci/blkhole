<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadFileChunk extends Model
{
    use HasFactory;

    public function file()
    {
        return $this->belongsTo(DownloadFile::class);
    }

    public function download()
    {
        return $this->file()->download();
    }


}

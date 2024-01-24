<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function files()
    {
        return $this->hasMany(DownloadFile::class);
    }

}

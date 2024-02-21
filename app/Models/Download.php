<?php

namespace App\Models;

use App\Enums\DownloadStatus;
use App\Jobs\DownloadJob;
use App\Jobs\DownloadFinalize;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Asantibanez\LaravelEloquentStateMachines\Traits\HasStateMachines;
use App\StateMachines\DownloadStatusStateMachine;
use Illuminate\Support\Facades\Storage;

class Download extends Model
{
    use HasFactory;
    Use HasStateMachines;

    public $stateMachines = [
        'status' => DownloadStatusStateMachine::class
    ];

    protected static function boot()
    {
        parent::boot();

        // Listen for the updating event and update the updated_at timestamp
        static::updating(function ($download) {
            $download->updated_at = now();

            // $download->status()->is(DownloadStatus::CANCELLED()
        });

        static::saved(function ($download) {

            if( $download->status()->is(DownloadStatus::DOWNLOAD_CLOUD() ) ) {
                //dump($download);
                //$dispatched = DownloadJob::dispatch($download);
            }

            if( $download->status()->is(DownloadStatus::PROCESSING()) ) {
                $dispatched = DownloadFinalize::dispatch($download);
            }

        });

        static::created(function ($download) {
            //ProcessDownload::dispatch($download);
        });
    }

    public function links(): HasMany {
        return $this->hasMany(DownloadLink::class);
    }

    /**
     * Calculate the progress of the download.
     */
    public function getProgress(): int {

        $progress = [];

        foreach ($this->links as $link) {
            $progress[] = $link->getProgress();
        }

        if(!empty($progress)) {
            return array_sum($progress) / count($progress);
        } else {
            return 0;
        }
    }

	// ====================================================================================
	// Helpers
	// ====================================================================================
	public static function getStatusAsString($status) {
		$statuses = array_flip(DownloadStatus::options());
		return $statuses[(int) $status];
	}


    public static function getActive() {
        // Query downloads with active status
        return Download::where('status', DownloadStatus::DOWNLOAD_LOCAL);
    }

	public static function getByPath($srcPath) {
		return Download::where('src_path', $srcPath);
	}

    public static function isPaused($id): bool {

		$globalPause = filter_var(Setting::get('paused'), FILTER_VALIDATE_BOOLEAN);

		if($globalPause) {
			return true;
		}

		// Find the Download instance with the given ID
		$download = Download::findOrFail($id);
	
		// Return whether the download is paused or not
		return (bool) $download->paused;
	}

	public static function pauseDownload($id): void {
		$download = Download::findOrFail($id);
		$download->paused = !$download->paused;
		$download->save();
	}

	public static function deleteDownload($id): void {
		// Find the Download model instance
		$download = Download::findOrFail($id);

		Storage::delete($download->src_path);

		// Delete the Download model instance
		$download->delete();
    }

	public static function saveDownloadPart($downloadId, $linkId,  $index, $data) {
		Log::debug("saveDownloadPart: " . $downloadId . " - " . $linkId . " - " . $index . " ");
	}
    
    public static function createChunksMeta($size)
    {
        // Retrieve chunk size from settings
        $chunkSize = (int) Setting::get('chunkSize');
        $chunkCount = (int) ceil($size / $chunkSize);

        $chunks = [];
        for ($i = 0; $i < $chunkCount; $i++) {
            $start = $i * $chunkSize;
            $end = (($i + 1) * $chunkSize) - 1;

            if ($i == $chunkCount - 1) {
                $end = $size;
            }

            $chunks[] = (object) [
                'start' => $start,
                'end' => $end
            ];
        }

        return $chunks;
    }

}

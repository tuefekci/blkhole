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

    private $pathWeb;


    function __construct() {
        parent::__construct();

        try {
			// Create necessary directories based on configuration
			Storage::makeDirectory( config('blkhole.paths.blackhole') );
	
			// Set paths for blackhole and downloads web interfaces
			$this->pathWeb = config('blkhole.paths.blackhole') . DIRECTORY_SEPARATOR . "web" . DIRECTORY_SEPARATOR;
	
			// Ensure the existence of specific directories
			Storage::makeDirectory($this->pathWeb);
		} catch (\Exception $e) {
			// Log any errors that occur during initialization
			Log::error("Error in constructor: " . $e->getMessage());
		}
    }

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

    public function isValidExtension($file): string {
		// Define the valid file extensions
		$validExtensions = ['ddl', 'torrent', 'magnet'];

		// Get the extension of the file
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		// Check if the extension is valid
		return in_array($extension, $validExtensions);
	}

    public static function addMagnet($magnetUrl) {
		try {
			// Check if the magnet URL starts with 'magnet:'
			if (\strpos($magnetUrl, 'magnet:') === false) {
				throw new \Exception("Invalid magnet URL: $magnetUrl");
			}
	
			$magnetRaw = $magnetUrl;
	
			// If the magnet URL contains percent-encoded characters, decode them
			if(preg_match('~%[0-9A-F]{2}~i', $magnetRaw)) {
				$magnetRaw = urldecode($magnetRaw);
			}
	
			// Extract hash, filename, and trackers from the magnet URL
			if (!preg_match('#magnet:\?xt=urn:btih:(?<hash>.*?)&dn=(?<filename>.*?)&tr=(?<trackers>.*?)$#', $magnetRaw, $magnet)) {
				throw new \Exception("Invalid magnet URL format: $magnetUrl");
			}
	
			// Check if filename is present and is a string
			if(!empty($magnet['filename']) && is_string($magnet['filename'])) {
				// Save the magnet URL to storage with the filename
				Storage::put($this->pathWeb . $magnet['filename'] . ".magnet", $magnetRaw);
				return true;
			} else {
				// Throw exception if filename is missing or not a string
				throw new \Exception("Missing or invalid filename in magnet URL: $magnetUrl");
			}
		} catch (\Exception $e) {
			// Log the error and return false
			Log::error("Error adding magnet URL: " . $e->getMessage());
			return false;
		}
	}

	public static function addTorrent($torrentPath, $torrentName) {

		// Define the allowed file extensions for torrent files
		$allowedExtensions = ['torrent'];

		try {
			// Check if the file exists
			if (!Storage::exists($torrentPath)) {
				throw new \Exception("Torrent file not found: $torrentPath");
			}
	
			// Check if the file has a valid MIME type for a torrent file
			$mimeType = Storage::mimeType($torrentPath);
			if ($mimeType !== 'application/x-bittorrent') {
				throw new \Exception("Invalid torrent file format: $torrentPath");
			}

			// Extract the file extension using pathinfo
			$fileInfo = pathinfo($torrentPath);

			// Check if the file extension is in the list of allowed extensions
			if (!in_array(strtolower($fileInfo['extension']), $allowedExtensions)) {
				throw new \Exception("Invalid torrent file format: $torrentPath");
			}

			$nameInfo = pathinfo($torrentName);

			// Additional checks or actions can be added here if needed
			// ...
	
			// If all checks pass, you can proceed with your logic
			Storage::move($torrentPath, $this->pathWeb . $nameInfo['filename'] . "." .$fileInfo['extension']);
			return true;
	
		} catch (\Exception $e) {
			// Log the error and return false
			Log::error("Error adding torrent file: " . $e->getMessage());
			return false;
		}
	}

	public static function addDDL($ddlUrl) {
		try {
			// Validate the URL format
			if (!filter_var($ddlUrl, FILTER_VALIDATE_URL)) {
				throw new \Exception("Invalid URL format: $ddlUrl");
			}
	
			// Check if the URL starts with 'http://' or 'https://'
			if (!preg_match('/^https?:\/\//', $ddlUrl)) {
				throw new \Exception("URL must start with 'http://' or 'https://': $ddlUrl");
			}
	
			// Additional checks or actions can be added here if needed
			// ...
	
	

			// If all checks pass, you can proceed with your logic
			Storage::put($this->pathWeb . base64_encode(UrlHelper::cleanUrl($ddlUrl)).".ddl", $ddlUrl);
			return true;
	
		} catch (\Exception $e) {
			// Log the error and return false
			Log::error("Error adding DDL URL: " . $e->getMessage());
			return false;
		}
	}

}

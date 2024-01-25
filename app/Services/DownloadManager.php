<?php

namespace App\Services;

use App\Models\Download;
use Error;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadManager
{
	private string $pathWeb;

	private array $statuses  = [
		'Pending',
		'In Progress',
		'Download Cloud',
		'Download Local',
		'Processing',
		'Completed',
		'Cancelled'
	];

	public function __construct()
	{
		try {
			// Create necessary directories based on configuration
			Storage::makeDirectory( config('blkhole.paths.downloads') );
	
			// Set paths for blackhole and downloads web interfaces
			$this->pathWeb = config('blkhole.paths.downloads') . DIRECTORY_SEPARATOR . "webinterface" . DIRECTORY_SEPARATOR;
	
			// Ensure the existence of specific directories
			Storage::makeDirectory($this->pathWeb);
	
		} catch (\Exception $e) {
			// Log any errors that occur during initialization
			Log::error("Error in constructor: " . $e->getMessage());
		}
	}

	// ====================================================================================
	// Helpers
	// ====================================================================================
	// TODO: Move to a global helper class ?

    public function getStatusAsString(int $index): ?string
    {
        return $this->statuses[$index] ?? null;
    }

    public function getStatusAsInt(string $status): ?int
    {
        $index = array_search($status, $this->statuses);
        return ($index !== false) ? $index : null;
    }

	public function isValidExtension($file) {
		// Define the valid file extensions
		$validExtensions = ['ddl', 'torrent', 'magnet'];

		// Get the extension of the file
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		// Check if the extension is valid
		return in_array($extension, $validExtensions);
	}


	// ====================================================================================
	// Logic
	// ====================================================================================

    public function getProgress($downloadId)
    {
        $download = Download::with('files.chunks')->find($downloadId);

        if (!$download) {
            Log::error('Download not found');
        }

        $totalChunks = $download->files->flatMap(function ($file) {
            return $file->chunks ?? [];
        })->count();

        $completedChunks = $download->files->flatMap(function ($file) {
            return $file->chunks->where('completed', true) ?? [];
        })->count();

        if ($totalChunks === 0) {
            return 0; // Avoid division by zero
        }

        $progressPercentage = ($completedChunks / $totalChunks) * 100;

        return $progressPercentage;
    }

	public function pollBlackhole()
	{
		$blackholePath = config('blkhole.paths.blackhole');
		$files = Storage::allFiles($blackholePath);
	
		foreach ($files as $file) {
			// Check if the file has a valid extension
			if ($this->isValidExtension($file)) {
				try {
					$fileName = pathinfo($file, PATHINFO_FILENAME);
					$fileType = pathinfo($file, PATHINFO_EXTENSION);
	
					if ($fileType === "ddl") {
						$fileName = base64_decode($fileName);
					}
	
					// Check if the download already exists
					if (!$this->downloadExists($file, $fileName, $fileType)) {
						$download = new Download();
						$download->name = $fileName;
						$download->status = 0;
						$download->src_path = $file;
						$download->src_type = $fileType;
	
						try {
							$debrid = DebridServiceFactory::createDebridService();
							$debridResponse = $debrid->add($fileType, Storage::get($file));
	
							if(!empty($debridResponse['name'])) {
								$download->name = $debridResponse['name'];
							}

							$download->debrid_provider = $debrid->getProviderName();
							$download->debrid_id = $debridResponse['id'];
							$download->debrid_status = $debridResponse['ready'];
	
							$download->save();
						} catch (\Throwable $th) {
							Log::error("pollBlackhole->debrid Error: " . $th->getMessage());
						}
					} else {
						Log::debug("Download already exists for: " . $file);
					}
				} catch (\Throwable $th) {
					Log::error("pollBlackhole creating new Download failed for " . $file . " with error: " . $th->getMessage());
				}
			} else {
				Log::error("isValidExtension failed for: " . $file);
			}
		}
	}
	
	private function downloadExists($srcPath, $name, $srcType)
	{
		return Download::where('src_path', $srcPath)
			->where('src_type', $srcType)
			->exists();
	}

	public function pollDownloads() {

	}
}
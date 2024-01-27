<?php

namespace App\Services;

use App\Models\Download;
use App\Models\DownloadLink;
use App\Models\DownloadLinkFile;
use Error;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadManager
{
	private string $pathWeb;

	private array $statuses  = [
		'Pending',
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

    public function getStatusAsString(int $index): ?string {
        return $this->statuses[$index] ?? null;
    }

    public function getStatusAsInt(string $status): ?int {
        $index = array_search($status, $this->statuses);
        return ($index !== false) ? $index : null;
    }

	public function getStatusCancelled(): ?int {
		// Return the last status
		return count($this->statuses) - 1;
	}

	public function isValidExtension($file): string {
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

	private function getDownloadByPath($srcPath) {
		return Download::where('src_path', $srcPath);
	}

	public function pauseDownload($id): void {
		$download = Download::findOrFail($id);
		$download->paused = !$download->paused;
		$download->save();
	}

	public function deleteDownload($id): void {
		// Find the Download model instance
		$download = Download::findOrFail($id);

		Storage::delete($download->src_path);

		// Delete the Download model instance
		$download->delete();
    }

	public function pollBlackhole(): void
	{
		$blackholePath = config('blkhole.paths.blackhole');
		$files = Storage::allFiles($blackholePath);
		$debrid = DebridServiceFactory::createDebridService();
	
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
					if ($this->getDownloadByPath($file)->exists()) {
						$download = $this->getDownloadByPath($file)->first();
					} else {
						$download = new Download();
					}

					$download->name = $fileName;
					$download->status = 1;
					$download->src_path = $file;
					$download->src_type = $fileType;

					try {
						$debridResponse = $debrid->add($fileType, Storage::get($file));

						if(!empty($debridResponse['name'])) {
							$download->name = $debridResponse['name'];
						}

						$download->debrid_provider = $debrid->getProviderName();
						$download->debrid_id = $debridResponse['id'];
						$download->debrid_status = $fileType." ".__('add success');
						$download->save();
					} catch (\Throwable $th) {
						Log::error("pollBlackhole->debrid Error: " . $th->getMessage());

						$download->status = $this->getStatusCancelled();
						$download->debrid_id = 0;
						$download->debrid_provider = $debrid->getProviderName();
						$download->debrid_status = $th->getMessage();
						$download->save();
					}

				} catch (\Throwable $th) {
					Log::error("pollBlackhole creating new Download failed for " . $file . " with error: " . $th->getMessage());
				}
			} else {
				Log::error("isValidExtension failed for: " . $file);
			}
		}
	}

	public function pollDownloads() {

		// Instantiate Debrid Service
		$debrid = DebridServiceFactory::createDebridService();

		// Retrieve all downloads from the database
		$downloads = Download::where('status', '!=', $this->getStatusCancelled())->get();

		// Loop through each download
		foreach ($downloads as $download) {
			
			// TODO: This can be removed because the where condition should already capture cancelled downloads.
			if(empty($download->debrid_id)) {
				continue;
			}

			$downloadDebridStatus = $debrid->getStatus($download->debrid_id);

			if(empty($downloadDebridStatus)) {
				// This should in theory only happen with the api test inputs but perhaps as well with removed magnets?
				// Todo: If this also happens when magnets are timeout perhaps send them back to blackhole polling or just reset the debrid ids and add them again here???
				// Todo: Figure out how to handle failed magnets, probably should be a job with long wait times so we have a chance to get temporary unavailable stuff.
				Log::error("pollDownloads->downloadDebridStatus result is empty!?");
				$download->debrid_status = "Empty Debrid Status Response?";
				$download->status = $this->getStatusCancelled();
				$download->save();
				continue;
			}

			try {
				if($downloadDebridStatus['status'] === "error") {
					$download->status = $this->getStatusCancelled();
				} elseif($downloadDebridStatus['status'] === "processing") {
					$download->status = 1;
				} elseif($downloadDebridStatus['status'] === "ready") {

					$download->status = 2;
					dump($downloadDebridStatus);
					foreach ($downloadDebridStatus['links'] as $link) {
						dump($link);

						if(DownloadLink::where('link', $link['link'])->exists()) {
							continue;
						}

						// Create a new download_link instance
						$downloadLink = new DownloadLink([
							'filename' => $link['filename'],
							'link' => $link['link'],
						]);

						// Save the download_link
						//$downloadLink->save();

						// Associate the download_link with the parent download
						$download->links()->save($downloadLink);

						// Create and associate files with the download link
						foreach ($link['files'] as $fileData) {
							$file = new DownloadLinkFile([
								'name' => $fileData['n'],
								'size' => $fileData['s'],
							]);

							// Save the file
							//$file->save();

							// Associate the file with the download link
							$downloadLink->files()->save($file);
						}
					}
	
				}
	
				$download->debrid_status = $downloadDebridStatus['debridStatusMessage'];
				$download->save();
			} catch (\Throwable $th) {
				Log::error($th->getMessage());
				//throw $th;
				dump($th);
				dump($downloadDebridStatus);
			}



		}
	}
}
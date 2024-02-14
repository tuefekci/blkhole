<?php

namespace App\Services;

use App\Models\Download;
use App\Models\DownloadLink;
use App\Models\DownloadLinkFile;
use App\Enums\DownloadStatus;
use App\Jobs\ProcessDownload;
use App\Models\Setting;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadManager
{
	private string $pathWeb;

	public function __construct()
	{
		try {
			// Create necessary directories based on configuration
			Storage::makeDirectory( config('blkhole.paths.downloads') );
	
			// Set paths for blackhole and downloads web interfaces
			$this->pathWeb = config('blkhole.paths.downloads') . DIRECTORY_SEPARATOR . "web" . DIRECTORY_SEPARATOR;
	
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
	public function getStatusAsString($status) {
		$statuses = array_flip(DownloadStatus::options());
		return $statuses[$status];
	}


	// ====================================================================================
	// Logic
	// ====================================================================================




	public function pollDownloads() {

		// Instantiate Debrid Service
		$debrid = DebridServiceFactory::createDebridService();

		// Retrieve all downloads from the database
		$downloads = Download::where('status', '!=', DownloadStatus::CANCELLED)->get();

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
				$download->status = DownloadStatus::CANCELLED;
				$download->save();
				continue;
			}

			$download->debrid_status = $downloadDebridStatus['debridStatusMessage'];

			try {
				if($downloadDebridStatus['status'] === "error") {
					$download->status = DownloadStatus::CANCELLED;
				} elseif($downloadDebridStatus['status'] === "processing") {
					$download->status = DownloadStatus::DOWNLOAD_CLOUD;
				} elseif($downloadDebridStatus['status'] === "ready") {
					$download->status = DownloadStatus::DOWNLOAD_PENDING;

					foreach ($downloadDebridStatus['links'] as $link) {

						if(DownloadLink::where('link', $link['link'])->exists()) {
							continue;
						}

						// Create a new download_link instance
						$downloadLink = new DownloadLink([
							'filename' => $link['filename'],
							'link' => $link['link'],
						]);

						// Associate the download_link with the parent download
						$download->links()->save($downloadLink);

						// Create and associate files with the download link
						foreach ($link['files'] as $fileData) {
							$file = new DownloadLinkFile([
								'name' => $fileData['n'],
								'size' => $fileData['s'],
							]);

							// Associate the file with the download link
							$downloadLink->files()->save($file);
						}
					}



				}
			} catch (\Throwable $th) {
				Log::error($th->getMessage());
				//throw $th;
			}

			try {
				// Schedule Download Job
				Log::info("ProcessDownload::dispatch ".$download->id);
				ProcessDownload::dispatch($download);
			} catch (\Throwable $th) {
				Log::error($th->getMessage());
				//throw $th;
			}

			$download->save();



		}
	}
}
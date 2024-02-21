<?php

namespace App\Services;

use App\Enums\DownloadStatus;
use App\Helpers\UrlHelper;
use App\Jobs\DownloadJob;
use App\Models\Download;
use Carbon\Carbon;
use Error;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BlackholeManager
{
	private string $pathWeb;

	public function __construct()
	{
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
	
	public function addMagnet($magnetUrl) {
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

	public function addTorrent($torrentPath, $torrentName) {

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

	public function addDDL($ddlUrl) {
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
	
	public function isValidExtension($file): string {
		// Define the valid file extensions
		$validExtensions = ['ddl', 'torrent', 'magnet'];

		// Get the extension of the file
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		// Check if the extension is valid
		return in_array($extension, $validExtensions);
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
					if (Download::getByPath($file)->exists()) {
						$download = Download::getByPath($file)->first();

						if($download->status()->is(DownloadStatus::CANCELLED())) {

							$cancelHistory  = $download->status()->history()->transitionedTo(DownloadStatus::CANCELLED())->get();

							if ($cancelHistory->count() > 50 && Carbon::parse($cancelHistory->last()->created_at)->diffInMinutes(Carbon::now()) < 60*6) {
								// Check if the cancelCount is greater than 50 and if the last cancellation was less than 60*6 minutes ago
								// skip so we don´t flood the api
								continue;
							} else if ($cancelHistory->count() > 15 && Carbon::parse($cancelHistory->last()->created_at)->diffInMinutes(Carbon::now()) < 60) {
								// Check if the cancelCount is greater than 15 and if the last cancellation was less than 60 minutes ago
								// skip so we don´t flood the api
								continue;
							} else if ($cancelHistory->count() > 5 && Carbon::parse($cancelHistory->last()->created_at)->diffInMinutes(Carbon::now()) < 15) {
								// Check if the cancelCount is greater than 5 and if the last cancellation was less than 15 minutes ago
								// skip so we don´t flood the api
								continue;
							} else if (Carbon::parse($cancelHistory->last()->created_at)->diffInMinutes(Carbon::now()) < 2) {
								// skip so we don´t flood the api
								continue;
							} else {
								// reset to pending
								$download->status()->transitionTo(DownloadStatus::PENDING(), [
									'comments' => "[blkhole] " .__('Auto Restart') . " (" . $cancelHistory->count() . ")",
								]);
							}

						} else {

							// TODO: There must be a nicer way to handle this? But for the moment i am fed up with sqlite file locks.
							if(!$download->status()->is(DownloadStatus::COMPLETED()) || !$download->status()->is(DownloadStatus::STREAM())) {
								if(now()->diffInHours($download->updated_at) > 1) {
									// reset to pending
									$download->status()->transitionTo(DownloadStatus::PENDING(), [
										'comments' => "[blkhole] " .__('Auto Restart') . " (TIMEOUT)",
									]);
								}
							}

						}
					} else {
						$download = new Download();
					}


					if($download->status == null || $download->status()->is(DownloadStatus::PENDING())) {
						try {
							$download->name = $fileName;
							$download->src_path = $file;
							$download->src_type = $fileType;
							$download->save();	

							$debridResponse = $debrid->add($fileType, Storage::get($file));

							if(!empty($debridResponse['name'])) {
								$download->name = $debridResponse['name'];
							}

							$download->debrid_provider = $debrid->getProviderName();
							$download->debrid_id = $debridResponse['id'];
							$download->save();


							$download->status()->transitionTo(DownloadStatus::DOWNLOAD_CLOUD(), [
								'comments' => "[" . __($debrid->getProviderName()) . '] ' . __($fileType)." ".__('add success'),
							]);

							$dispatched = DownloadJob::dispatch($download);

							echo "[blkhole] ". __($fileType)." ".__('dispatched'). " ". $file. "\n";

						} catch (\Throwable $th) {
							Log::error("pollBlackhole->debrid Error: " . $th->getMessage());

							$download->debrid_id = 0;
							$download->debrid_provider = $debrid->getProviderName();

							$download->status()->transitionTo(DownloadStatus::CANCELLED(), [
								'comments' => "[" . __($debrid->getProviderName()) . '] '. __($th->getMessage()),
							]);

							$download->save();

							echo "[blkhole] ". __($fileType)." ".__($th->getMessage()). " ". $file. "\n";
						}

					} else {
						echo "[blkhole] ". __($fileType)." ".__('already dispatched'). " ". $file. "\n";
					}



				} catch (\Throwable $th) {
					Log::error("pollBlackhole creating new Download failed for " . $file . " with error: " . $th->getMessage());
					echo "[blkhole] ". __("pollBlackhole creating new Download failed for " . $file . " with error: " . $th->getMessage());
				}
			} else {
				Log::error("isValidExtension failed for: " . $file);
				echo "[blkhole] ". __("isValidExtension failed for: " . $file). "\n";
			}
		}
	}
	
}
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
							} else if (Carbon::parse($cancelHistory->last()->created_at)->diffInMinutes(Carbon::now()) < 1) {
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

							DownloadJob::dispatch($download);

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
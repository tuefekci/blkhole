<?php

namespace App\Jobs;

use App\Enums\DownloadStatus;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDownload implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $download;

    public $timeout = 60*60*6;
    public $tries = 1000*1000;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Download $download) {
        $this->download = $download;
    }

     /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string {
        return sha1($this->download->id);
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {
        $downloadManager = app('DownloadManager');

        Log::info("Handle ProcessDownload for Download: " . $this->download->id);

        $activeDownloadsCount = $downloadManager->getActiveDownloads()->count();
        $parallelDownloadsSetting = (int) Setting::get('parallel');
        
        if ($activeDownloadsCount >= $parallelDownloadsSetting) {
            Log::info("Skipping Download " . $this->download->id . " due to parallel download setting | Active Downloads: " . $activeDownloadsCount . " | Parallel Setting: ".$parallelDownloadsSetting);
            $this->release(Carbon::now()->addSeconds(15));
            return;
        } else {

            $this->download->status = DownloadStatus::DOWNLOAD_LOCAL;
            $this->download->save();
            
    
            Log::info("Download Active: ".$this->download->id);
            sleep(120);
    
            // Set back to pending
            $this->download->status = DownloadStatus::DOWNLOAD_PENDING;
            $this->download->save();
    
            $this->release(now()->addSeconds(10));

        }
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed($exception): void
    {
        // Set status back to pending download
        // Todo: perhaps resetting it completely to pending would be a better choice?
        $this->download->status = DownloadStatus::DOWNLOAD_PENDING;
        $this->download->save();
    }
}

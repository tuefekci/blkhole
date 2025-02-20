<?php

namespace App\Jobs;

use App\Jobs\Middleware\DownloadPaused;
use App\Enums\DownloadStatus;
use App\Models\Download;
use App\Models\DownloadLink;
use App\Models\DownloadLinkFile;
use App\Models\Setting;
use App\Services\DebridServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use romanzipp\QueueMonitor\Traits\IsMonitored;

use DateTime;

class DownloadJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, IsMonitored;

    protected $download;


    /**
    * Calculate the number of seconds to wait before retrying the job.
    *
    * @return array<int, int>
    */
    public function backoff(): array
    {
        return [30, 60, 90, 120, 180];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 6;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60*5;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Download $download) {
        $this->download = $download;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new DownloadPaused($this->download->id))];
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 60*60*6;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->download->id;
    }

    /**
     * Get the cache driver for the unique job lock.
     */
    public function uniqueVia(): Repository
    {
        return Cache::driver('database');
    }

    /**
     * Set MonitorData for the job.
     * @return array<string, mixed>
     * */
    public function initialMonitorData()
    {
        return ['download_id' =>  $this->download->id];
    }
    
    public function progressCooldown(): int
    {
        return 10; // Wait 10 seconds between each progress update
    }

    public static function keepMonitorOnSuccess(): bool
    {
        return false;
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {

        try {

            Log::info("Handle ProcessDownload for Download: " . $this->download->id);
    
            // Instantiate Debrid Service
            $debrid = DebridServiceFactory::createDebridService($this->download->debrid_provider);
            $downloadDebridStatus = $debrid->getStatus($this->download->debrid_id);
    
            if(empty($downloadDebridStatus)) {   
                // Handle empty debrid result!
                throw new \Exception("processDownload->downloadDebridStatus result is empty!?");
                return;
            } elseif ($downloadDebridStatus['status'] === "error") {
                // Handle error debrid result!
                throw new \Exception($downloadDebridStatus['debridStatusMessage']);
                return;
            } elseif($downloadDebridStatus['status'] === "processing") {
                $this->download->status()->transitionTo(DownloadStatus::DOWNLOAD_CLOUD(), [
                    'comments' => "[" . __($debrid->getProviderName()) . '] '. __($downloadDebridStatus['debridStatusMessage']),
                ]);

                $this->release(now()->addSeconds(60*5));
                return;
            } elseif($downloadDebridStatus['status'] === "ready") {

                $this->processDebridLinks($downloadDebridStatus);

                $skipBatching = false;
                if($this->download->batch_id)  {
                    $skipBatching = true;
                    $batch = Bus::findBatch($this->download->batch_id);

                    if($batch->finished() || $batch->cancelled()) {
                        $skipBatching = false;
                    }
                }


                if($skipBatching) {
                    $this->release(now()->addSeconds(120));
                    return;
                } else {

                    $this->download->status()->transitionTo(DownloadStatus::DOWNLOAD_PENDING(), [
                        'comments' => "[blkhole] ". __("Job Download"),
                    ]);

                    $linkBatch = [];

                    // Retrieve all downloads with their associated links
                    $download = Download::with('links')->find($this->download->id);

                    foreach ($download->links as $link) {
                        echo $link->url . PHP_EOL;
                        $linkBatch[] = new DownloadLinkJob($link);
                    }

                    /**
                     * Creates a batch job to process the download chunks in parallel. 
                     * Dispatches the batch job and saves the batch ID on the download link.
                     *
                     * @param DownloadChunk[] $chunkBatch Array of chunk objects to process in parallel
                     */
                    if (!empty($linkBatch)) {
                        $batch = Bus::batch($linkBatch)->progress(function (Batch $batch) {
                            // A single job has completed successfully...
                        })->then(function (Batch $batch) {
                            // All jobs completed successfully...

                            /*
                            $this->queueProgress(100);

                            $this->download->status()->transitionTo(DownloadStatus::PROCESSING(), [
                                'comments' => "[blkhole] ". __("Downloads Completed."),
                            ]);*/

                            // TODO: Dispatch finalized job here

                        })->catch(function (Batch $batch, \Throwable $e) {
                            // First batch job failure detected...
                        })->finally(function (Batch $batch) {
                            // The batch has finished executing...
                            // TODO: add error handling here
                        })->name('Batch Download[' . $this->download->id . "]")->dispatch();

                        $this->download->batch_id = $batch->id;
                        $this->download->save();
                    }

                }

            }

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            throw $th;
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
        Log::error($exception->getMessage());

        if($this->download->batch_id) {
            $batch = Bus::findBatch($this->download->batch_id);
            $batch->cancel();
        }

        // Set status to CANCELLED
        $this->download->status()->transitionTo(DownloadStatus::CANCELLED(), [
            'comments' => "[blkhole] ". __($exception->getMessage()),
        ]);
    }

    private function processDebridLinks($downloadDebridStatus) {

        foreach ($downloadDebridStatus['links'] as $link) {

            if (DownloadLink::where('url', $link['link'])->exists()) {
                $downloadLink = DownloadLink::where('url', $link['link'])->first();
            } else {
                // Create a new download_link instance
                $downloadLink = DownloadLink::create([
                    'filename' => $link['filename'],
                    'url' => $link['link'],
                    'download_id' => $this->download->id
                ]);

                $downloadLink->save();

                // Associate the download_link with the parent download
                $this->download->links()->save($downloadLink);

                // Create and associate files with the download link
                foreach ($link['files'] as $fileData) {
                    $file = DownloadLinkFile::create([
                        'name' => $fileData['n'],
                        'size' => $fileData['s'],
                        'download_link_id' => $downloadLink->id
                    ]);

                    $file->save();

                    // Associate the file with the download link
                    $downloadLink->files()->save($file);
                }
            }

        }
    }

}

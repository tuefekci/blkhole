<?php

namespace App\Jobs;

use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class DownloadLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable, IsMonitored;

    protected $downloadLink;

    /** 
     * Create a new job instance.
     *
     * @param  \App\Models\DownloadLink  $downloadLink
     * @return void
     */
    public function __construct(\App\Models\DownloadLink $downloadLink)
    {
        $this->downloadLink = $downloadLink;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new DownloadPaused($this->downloadLink->download->id))];
    }

     /**
     * Set MonitorData for the job.
     * @return array<string, mixed>
     * */
    public function initialMonitorData()
    {
        return [
            'download_id' =>  $this->downloadLink->download->id,
            'download_link_id' =>  $this->downloadLink->id
        ];
    }
    
    public function progressCooldown(): int
    {
        return 10; // Wait 10 seconds between each progress update
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Your logic for processing the download link goes here
        try {
            Log::info("Handle ProcessDownloadLink for DownloadLink: " . $this->downloadLink->id);


            $skipBatching = false;
            if($this->downloadLink->batch_id)  {
                $skipBatching = true;
                $batch = Bus::findBatch($this->downloadLink->batch_id);

                if($batch->finished() || $batch->cancelled()) {
                    $skipBatching = false;
                }
            }

            if($skipBatching) {
                $this->release(now()->addSeconds(120));
                return;
            } else {


                /**
                 * Creates an array of DownloadChunk objects that represent chunks of the download link, 
                 * based on the chunk metadata calculated from the content length. This splits the download 
                 * into multiple chunks that can be processed in parallel.
                 */
                $chunkBatch = [];
                $chunks = Download::createChunksMeta($this->getContentLength($this->downloadLink->url));
                foreach ($chunks as $partIndex => $chunk) {
                    $chunkBatch[] = new DownloadChunk($this->downloadLink, $partIndex, $chunk->start, $chunk->end);
                }

                /**
                 * Creates a batch job to process the download chunks in parallel. 
                 * Dispatches the batch job and saves the batch ID on the download link.
                 *
                 * @param DownloadChunk[] $chunkBatch Array of chunk objects to process in parallel
                 */
                if (!empty($chunkBatch)) {

                    $batchCount = count($chunkBatch);

                    $batch = Bus::batch($chunkBatch)->progress(function (Batch $batch) {
                        // A single job has completed successfully...
                        //$this->queueProgressChunk($batchCount, 1);
                    })->then(function (Batch $batch) {
                        // All jobs completed successfully...
                        //$this->queueProgress(100);
                    })->catch(function (Batch $batch, \Throwable $e) {
                        // First batch job failure detected...
                    })->finally(function (Batch $batch) {
                        // The batch has finished executing...
                    })->name('Batch Download[' . $this->downloadLink->download->id . "] Link[" . $this->downloadLink->id . "]")->dispatch();

                    $this->downloadLink->batch_id = $batch->id;
                    $this->downloadLink->save();
                }

            }

            // Process the download link based on the content length or other parameters
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            // Handle exceptions
        }
    }

    /**
     * Get Headers
     *
     * Gets the headers for the requested download so we can determine how many chunks etc.
     */
    private function getContentLength($url)
    {
        Log::debug("[ProcessDownloadLink] getContentLength - " . $url);

        try {
            $response = Http::head($url);

            $statusCode = $response->status();
            if ($statusCode !== 200) {
                throw new \Exception("Invalid response code: $statusCode");
            }

            $contentLength = $response->header('content-length');
            if (empty($contentLength)) {
                throw new \Exception("Download getHeaders content-length empty.");
            }

            return (int) $contentLength;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}

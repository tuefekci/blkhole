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

class DownloadJob implements ShouldQueue
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
        return [30, 60, 120];
    }

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60*2;

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
     * Set MonitorData for the job.
     * @return array<string, mixed>
     * */
    public function initialMonitorData()
    {
        return ['download_id' =>  $this->download->id];
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new DownloadPaused($this->download->id)), (new WithoutOverlapping($this->download->id))->expireAfter(60*10)];
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {

        try {

            Log::info("Handle ProcessDownload for Download: " . $this->download->id);

            $this->download->status()->transitionTo(DownloadStatus::DOWNLOAD_PENDING(), [
                'comments' => "[blkhole] ". __("Job Download"),
            ]);
    
            // Instantiate Debrid Service
            $debrid = DebridServiceFactory::createDebridService($this->download->debrid_provider);
            $downloadDebridStatus = $debrid->getStatus($this->download->debrid_id);
    
    


            // Handle empty debrid result!
            if(empty($downloadDebridStatus)) {
                // This should in theory only happen with the api test inputs but perhaps as well with removed magnets?
                // TODO: If this also happens when magnets are timeout perhaps send them back to blackhole polling or just reset the debrid ids and add them again here??? # <- This should already happen?
                // TODO: Figure out how to handle failed magnets, probably should be a job with long wait times so we have a chance to get temporary unavailable stuff. # <- Just cancel the blackhole poll should figure it out.
                Log::error("processDownload->downloadDebridStatus result is empty!?");
    
                $this->download->status()->transitionTo(DownloadStatus::CANCELLED(), [
                    'comments' => "[" . __($debrid->getProviderName()) . '] '. __("Empty Debrid Status Response?"),
                ]);
    
                $this->fail("processDownload->downloadDebridStatus result is empty!?");
                return;
            }

            if($downloadDebridStatus['status'] === "error") {

                $this->download->status()->transitionTo(DownloadStatus::CANCELLED(), [
                    'comments' => "[" . __($debrid->getProviderName()) . '] '. __($downloadDebridStatus['debridStatusMessage']),
                ]);

                $this->fail($downloadDebridStatus['debridStatusMessage']);
                return;

            } elseif($downloadDebridStatus['status'] === "processing") {

                $this->download->status()->transitionTo(DownloadStatus::DOWNLOAD_CLOUD(), [
                    'comments' => "[" . __($debrid->getProviderName()) . '] '. __($downloadDebridStatus['debridStatusMessage']),
                ]);

                $this->release(now()->addSeconds(60*5));
                return;

            } elseif($downloadDebridStatus['status'] === "ready") {

                $this->download->status()->transitionTo(DownloadStatus::DOWNLOAD_LOCAL(), [
                    'comments' => "[" . __($debrid->getProviderName()) . '] '. __($downloadDebridStatus['debridStatusMessage']),
                ]);

                foreach ($downloadDebridStatus['links'] as $link) {

                    if (DownloadLink::where('url', $link['link'])->exists()) {
                        $downloadLink = DownloadLink::where('url', $link['link'])->first();
                    } else {
                        // Create a new download_link instance
                        $downloadLink = DownloadLink::create([
                            'filename' => $link['filename'],
                            'url' => $link['link']
                        ]);

                        // Associate the download_link with the parent download
                        $this->download->links()->save($downloadLink);

                        // Create and associate files with the download link
                        foreach ($link['files'] as $fileData) {
                            $file = DownloadLinkFile::create([
                                'name' => $fileData['n'],
                                'size' => $fileData['s']
                            ]);

                            // Associate the file with the download link
                            $downloadLink->files()->save($file);
                        }
                    }



                    try {

                        $skipBatching = false;
                        if($downloadLink->batch_id)  {
                            $skipBatching = true;
                            $batch = Bus::findBatch($downloadLink->batch_id);

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
                            $chunks = Download::createChunksMeta($this->getContentLength($downloadLink->url));
                            foreach ($chunks as $partIndex => $chunk) {
                                $chunkBatch[] = new DownloadChunk($downloadLink, $partIndex, $chunk->start, $chunk->end);
                            }

                            /**
                             * Creates a batch job to process the download chunks in parallel. 
                             * Dispatches the batch job and saves the batch ID on the download link.
                             *
                             * @param DownloadChunk[] $chunkBatch Array of chunk objects to process in parallel
                             */
                            if (!empty($chunkBatch)) {
                                $batch = Bus::batch($chunkBatch)->progress(function (Batch $batch) {
                                    // A single job has completed successfully...
                                })->then(function (Batch $batch) {
                                    // All jobs completed successfully...
                                    // TODO: Dispatch finalized job here
                                    $this->download->status()->transitionTo(DownloadStatus::PROCESSING(), [
                                        'comments' => "[" . __($debrid->getProviderName()) . '] '. __($downloadDebridStatus['debridStatusMessage']),
                                    ]);

                                })->catch(function (Batch $batch, Throwable $e) {
                                    // First batch job failure detected...
                                })->finally(function (Batch $batch) {
                                    // The batch has finished executing...
                                })->name('Download[' . $this->download->id . "] Link[" . $downloadLink->id . "]")->dispatch();

                                $downloadLink->batch_id = $batch->id;
                                $downloadLink->save();
                            }
                        }




                    } catch (\Throwable $th) {
                        Log::error($th->getMessage());

                        /*
                        $this->download->status()->transitionTo(DownloadStatus::CANCELLED(), [
                            'comments' => "[blkhole] ". __($th->getMessage()),
                        ]);*/


                        //$this->fail($th);
                        //throw $th;
                    }
                }

                /*
                if($this->download->getProgress() == 100) {
                    // Shedule finalize download
                    $this->download->status()->transitionTo(DownloadStatus::PROCESSING(), [
                        'comments' => "[blkhole] ". __("ready to finalize."),
                    ]);
                } else {
                    $this->download->status()->transitionTo(DownloadStatus::DOWNLOAD_PENDING(), [
                        'comments' => "[" . __($debrid->getProviderName()) . '] '. __("Download apparently not done set back to pending!"),
                    ]);

                    $this->release(now()->addSeconds(120));
                }*/

            }


        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            $this->download->status()->transitionTo(DownloadStatus::CANCELLED(), [
                'comments' => "[blkhole] ". __($th->getMessage()),
            ]);

            $this->fail($th);

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
        // TODO: perhaps resetting it completely to pending would be a better choice?
        $this->download->status()->transitionTo(DownloadStatus::PENDING(), [
            'comments' => "[blkhole] ". __($exception->getMessage()),
        ]);
    }

    /**
     * Get Headers
     *
     * Gets the headers for the requested download so we can determine how many chunks etc.
     */
    private function getContentLength($url)
    {
        Log::debug("[ProcessDownload] getHeaders " . $this->download->id ." - " . $url);

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

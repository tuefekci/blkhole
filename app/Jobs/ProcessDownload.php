<?php

namespace App\Jobs;

use App\Jobs\Middleware\DownloadPaused;
use App\Enums\DownloadStatus;
use App\Models\DownloadLinkChunk;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Pool;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessDownload implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $download;

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
    public $timeout = 60*60*3;

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
     * Get the unique ID for the job.
     */
    public function uniqueId(): string {
        return $this->download->id;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
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
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {
        $downloadManager = app('DownloadManager');

        Log::info("Handle ProcessDownload for Download: " . $this->download->id);

        $this->download->status = DownloadStatus::DOWNLOAD_PENDING;
        $this->download->save();

        $activeDownloadsCount = $downloadManager->getActiveDownloads()->count();
        $parallelDownloadsSetting = (int) Setting::get('parallel');
        
        if ($activeDownloadsCount >= $parallelDownloadsSetting) {
            Log::info("Skipping Download " . $this->download->id . " due to parallel download setting | Active Downloads: " . $activeDownloadsCount . " | Parallel Setting: ".$parallelDownloadsSetting);
            $this->release(now()->addSeconds(15));
            return;
        } else {

            Log::info("Download Active: ".$this->download->id);
            $this->download->status = DownloadStatus::DOWNLOAD_LOCAL;
            $this->download->save();

            foreach ($this->download->links as $link) {
                Log::info("Working on link " . $link->id);
                # code...

                try {

                    if(DownloadLinkChunk::where([
                        ['download_link_id', '=', $link->id],
                    ])->exists()) {
                        // TODO: Chunks exist add some handling somewhere to force regeneration of chunks if something happens.
                    } else {
                        $chunks = $this->createChunksMeta($this->getContentLength($link->link));

                        foreach ($chunks as $partIndex => $chunk) {
                            if(DownloadLinkChunk::where([
                                ['start_byte', '=', $chunk->start],
                                ['end_byte', '=', $chunk->end],
                                ['download_link_id', '=', $link->id],
                            ])->exists()) {
                                continue;
                            } else {
                                Log::info("Created chunk for link " . $link->id);
                                DownloadLinkChunk::create([
                                    'index' => $partIndex,
                                    'size' => $chunk->end - $chunk->start + 1,
                                    'start_byte' => $chunk->start,
                                    'end_byte' => $chunk->end,
                                    'download_link_id' => $link->id,
                                ]);
                            }
                        }

                        unset ($chunks);

                    }

                } catch (\Throwable $th) {
                    //throw $th;
                    Log::error($th->getMessage());
                    $this->download->status = DownloadStatus::DOWNLOAD_PENDING;
                    $this->download->save();
                    return;
                }
            }

            // ===============================
            // ====== Download Files
            // ===============================
            $this->downloadLinks();
            // ===============================
            // ===============================

            
            if($this->download->getProgress() == 100) {
                try {
                    // Shedule finalize download
                    throw new \Exception("Not Implemented!");
                } catch (\Throwable $th) {
                    Log::error($th->getMessage());

                    // Set back to pending
                    $this->download->status = DownloadStatus::DOWNLOAD_PENDING;
                    $this->download->save();
                    $this->release(now()->addSeconds(30));
                }
            } else {
                // Download apparently not done set back to pending
                $this->download->status = DownloadStatus::DOWNLOAD_PENDING;
                $this->download->save();
                $this->release(now()->addSeconds(30));
            }
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

    private function downloadLinks() {

        $connections = (int) Setting::get('connections');

        foreach ($this->download->links as $link) {
            $chunks = $link->chunks()->where('completed', false)->get();
            $pairs = $chunks->chunk($connections); // Split chunks into pairs of amount of $connections elements
            $this->downloadChunks($pairs, $link->link);
        }

    }

    public static function createChunksMeta($size)
    {
        // Retrieve chunk size from settings
        $chunkSize = (int) Setting::get('chunkSize'); // 32MB default
        $chunkCount = (int) ceil($size / $chunkSize);

        $chunks = [];
        for ($i = 0; $i < $chunkCount; $i++) {
            $start = $i * $chunkSize;
            $end = (($i + 1) * $chunkSize) - 1;

            if ($i == $chunkCount - 1) {
                $end = $size;
            }

            $chunks[] = (object) [
                'start' => $start,
                'end' => $end
            ];
        }

        return $chunks;
    }


}

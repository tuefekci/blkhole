<?php

namespace App\Jobs;

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
        return $this->download->id;
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

    /**
     * Execute the job.
     * @return void
     */
    public function handle(): void
    {
        $downloadManager = app('DownloadManager');

        if($downloadManager->isPaused($this->download->id)) {
            Log::info("Skipping Download " . $this->download->id . " due to pause");
            $this->release(now()->addSeconds(30));
            return;
        }

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

            $connections = (int) Setting::get('connections');
            $bandwidth = (int) Setting::get('bandwidth');



            foreach ($this->download->links as $link) {
                $chunks = $link->chunks()->where('completed', false)->get();
                $pairs = $chunks->chunk($connections); // Split chunks into pairs of amount of $connections elements

                foreach ($pairs as $pair) {

                    if($downloadManager->isPaused($this->download->id)) {
                        Log::info("Skipping Download " . $this->download->id . " due to pause");
                        $this->release(now()->addSeconds(30));
                        return;
                    }
                    
                    $responses = Http::pool(function(Pool $pool) use($pair, $link) {
                        foreach($pair as $key => $chunk) {
                            $pool->as($key)->withHeaders(['Range' => "bytes={$chunk->start_byte}-{$chunk->end_byte}"])->timeout(60)->get($link->link);
                        }
                    });

                    foreach($responses as $key => $response) {
                        $chunk = $pair[$key];

                        try {
                            if( $response->status() === 206) {
                                $stats = $response->handlerStats();
                                //$downloadManager->saveDownloadPart($this->download->id, $link->id, $chunk->index, $response->body());
     
    
                                $chunk->download_time = $stats['total_time'];
                                $chunk->download_speed = $stats['speed_download'];
                                $chunk->completed = true;
                                $chunk->save();
    
                                // TODO: Update Database
                                Log::debug("Request for Chunk " .  $chunk->id . " with following stats: " . $stats['total_time'] . " | " . $stats['speed_download']);
                            } else {
                                Log::error("Request for Chunk " .  $chunk->id . " failed with status " . $response->status());
                            }
                        } catch (\Throwable $th) {
                            //throw $th;
                            Log::error($th->getMessage());
                        }
                    }

                    unset($responses);
                }
            }

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
}

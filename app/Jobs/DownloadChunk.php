<?php

namespace App\Jobs;

use App\Jobs\Middleware\DownloadPaused;
use App\Models\DownloadLinkChunk;
use App\Services\DownloadManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected \App\Models\DownloadLinkChunk $chunk;

    public $timeout = 60;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($chunkId) {
        $this->chunk = DownloadLinkChunk::findOrFail($this->chunk->id);
    }

     /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string {
        return $this->chunk->id;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new DownloadPaused($this->chunk->download()->id))];
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if is already completed??
        if($this->chunk->completed) {
            Log::warn("Skipping Chunk " . $this->chunk->id . " due to complete status.");
            throw new Exception("Skipping Chunk " . $this->chunk->id . " due to complete status.");
        }


        // TODO: Replace with better Settings solution! (TODO here only to find it.)
        $bandwidth = (int) Setting::get('bandwidth');

        try {

            // Set started so when can calculate the amount of parallel downloads etc.
            $this->chunk->started = true;
            $this->chunk->save();

            // Request chunk TODO: Add curl bandwith limiting!
            $response = Http::withHeaders(['Range' => "bytes={$this->chunk->start_byte}-{$this->chunk->end_byte}"])
            ->timeout(60)
            ->get($this->chunk->file()->file);

            // 206 = partial which is what we are requesting!
            if( $response->status() === 206) {
                $stats = $response->handlerStats();

                try {
                    // TODO: This should be smarter and perhaps replace the whole request with a stream if possible??
                    DownloadManager::saveDownloadPart(
                        $this->chunk->download()->id, 
                        $this->chunk->link()->id, 
                        $this->chunk->index, 
                        $response->body()
                    );
                } catch (\Throwable $th) {
                    throw $th;
                }

                $this->chunk->download_time = $stats['total_time'];
                $this->chunk->download_speed = $stats['speed_download'];
                $this->chunk->completed = true;
                $this->chunk->save();

                // TODO: Update Database
                Log::debug("Request Chunk " .  $this->chunk->id . " with following stats: " . $stats['total_time'] . " | " . $stats['speed_download']);
            } else {
                throw new Exception("Request Chunk " .  $this->chunk->id . " failed with status " . $response->status());
            }

        } catch (\Throwable $th) {
            throw $th;
        }

    }
}

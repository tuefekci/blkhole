<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $download;

    /**
     * Create a new job instance.
     */
    public function __construct(Download $download)
    {
        $this->download = $download;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
            // Access download details
            //$downloadUrl = $this->download->url;
            //$fileName = $this->download->filename;

            // Access settings
            //$parallelDownloads = config('download.parallel_downloads');
            //$connectionsPerDownload = config('download.connections_per_download');
            //$maxBandwidth = config('download.max_bandwidth');
            //$chunkSize = config('download.chunk_size');


    }
}

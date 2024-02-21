<?php

namespace App\Jobs;

use App\Jobs\Middleware\DownloadPaused;
use App\Services\DownloadManager;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class DownloadChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected \App\Models\DownloadLink $link;
    private $index;
    private $start;
    private $end;

    public $timeout = 60*10;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($downloadLink, $index, $start, $end) {
        $this->link = $downloadLink;
        $this->index = $index;
        $this->start = $start;
        $this->end = $end;
    }

     /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string {
        return $this->link->id."-chunk-".$this->index;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new DownloadPaused($this->link->download->id))];
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {

        sleep(1);

    }
}

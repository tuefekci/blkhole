<?php

namespace App\Jobs\Middleware;

use App\Models\Download;
use Closure;
use Illuminate\Support\Facades\Log;

class DownloadPaused
{

    private $downloadId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $downloadId) {
        $this->downloadId = $downloadId;
    }

    /**
     * Process the queued job.
     *
     * @param  \Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        // Check if paused
        if(Download::isPaused($this->downloadId)) {
            Log::info("Releasing ".get_class($job)." due to paused download. | Download ID " . $this->downloadId);
            $job->release(now()->addSeconds(60));
            return;
            //throw new Exception("Skipping Download " . $this->download->id . " due to pause.");
        } else {
            $next($job);
        }
    }
}

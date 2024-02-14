<?php

namespace App\Jobs\Middleware;

use App\Services\DownloadManager;
use Closure;
use Illuminate\Support\Facades\Log;
use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;

class DownloadLimit
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
        $parallel = KVOption::get('parallel', 0);

        if((int) Setting::get('parallel') > (int) $parallel) {
            Log::info("Releasing ".get_class($job)." due to active Download amount. | Download ID " . $this->downloadId);
            $job->release(now()->addSeconds(60));
            return;
        } else {
            KVOption::increment('parallel', 1);
            try {
                $next($job);
                KVOption::decrement('parallel', 1);
            } catch (\Throwable $th) {
                KVOption::decrement('parallel', 1);
                throw $th;
            }
        }

    }
}

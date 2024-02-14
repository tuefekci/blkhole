<?php

namespace App\Jobs\Middleware;

use App\Models\Setting;
use App\Services\DownloadManager;
use Closure;
use Illuminate\Support\Facades\Log;
use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;

class DownloadChunkLimit
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
        $connections = KVOption::get('connections-' . $this->downloadId, 0);

        if((int) Setting::get('connections') > (int) $connections) {
            Log::info("Releasing ".get_class($job)." due to active Connection amount. | Download ID " . $this->downloadId);
            $job->release(now()->addSeconds(15));
            return;
        } else {
            KVOption::increment('connections-' . $this->downloadId, 1);
            try {
                $next($job);
                KVOption::decrement('connections-' . $this->downloadId, 1);

                if((int) KVOption::get('connections-' . $this->downloadId) == 0) {
                    KVOption::remove('connections-' . $this->downloadId);
                }
            } catch (\Throwable $th) {
                KVOption::decrement('connections-' . $this->downloadId, 1);

                if((int) KVOption::get('connections-' . $this->downloadId) == 0) {
                    KVOption::remove('connections-' . $this->downloadId);
                }

                throw $th;
            }
        }

    }
}

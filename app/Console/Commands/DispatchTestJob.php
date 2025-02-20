<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\TestJob;

class DispatchTestJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dispatch:test-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a test job';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        var_dump(config('queue.default'));

        for ($i=0; $i < 30; $i++) { 
            // Dispatch the test job
            dispatch(new TestJob());
        }

        $this->info('Test job dispatched successfully.');

        return 0;
    }
}

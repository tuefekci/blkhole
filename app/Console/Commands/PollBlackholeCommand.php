<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollBlackholeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poll:blackhole';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll the blackhole for new downloads.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Call your pollBlackhole function
        $this->info('Polling blackhole...');

        try {
            app("BlackholeManager")->pollBlackhole();
            $this->info('Polling completed.');
        } catch (\Throwable $th) {
           Log::error("command poll:blackhole error:" . $th->getMessage());
           $this->error($th->getMessage());
           throw $th;
        }
    }
}

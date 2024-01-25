<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PollDownloadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poll:downloads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Call your pollBlackhole function
        $this->info('Polling downloads...');

        try {
            app("DownloadManager")->pollDownloads();
            $this->info('Polling completed.');
        } catch (\Throwable $th) {
           Log::error("command poll:downloads error:" . $th->getMessage());
           $this->error($th->getMessage());
        }
    }
}

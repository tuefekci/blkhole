<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\DownloadFile;
use App\Models\DownloadFileChunk;
use Illuminate\Console\Command;

class ClearDownloadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:downloads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears the Download, DownloadFile, and DownloadChunk models';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Clear Download models
        $this->info('Clearing Download models...');
        Download::truncate();
        $this->info('Download models cleared.');

        // Clear DownloadFile models
        $this->info('Clearing DownloadFile models...');
        DownloadFile::truncate();
        $this->info('DownloadFile models cleared.');

        // Clear DownloadChunk models
        $this->info('Clearing DownloadChunk models...');
        DownloadFileChunk::truncate();
        $this->info('DownloadChunk models cleared.');

        $this->info('Downloads cleared successfully.');
    }
}

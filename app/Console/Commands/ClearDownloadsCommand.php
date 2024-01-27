<?php

namespace App\Console\Commands;

use App\Models\Download;
use App\Models\DownloadLink;
use App\Models\DownloadLinkFile;
use App\Models\DownloadLinkChunk;
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

        // Clear DownloadLinkFile models
        $this->info('Clearing DownloadLinkFile models...');
        DownloadLinkFile::truncate();
        $this->info('DownloadLinkFile models cleared.');

        // Clear DownloadLinkChunk models
        $this->info('Clearing DownloadLinkChunk models...');
        DownloadLinkChunk::truncate();
        $this->info('DownloadLinkChunk models cleared.');

        // Clear DownloadLink models
        $this->info('Clearing DownloadLink models...');
        DownloadLink::truncate();
        $this->info('DownloadLink models cleared.');

        // Clear Download models
        $this->info('Clearing Download models...');
        Download::truncate();
        $this->info('Download models cleared.');

        $this->info('Downloads cleared successfully.');
    }
}

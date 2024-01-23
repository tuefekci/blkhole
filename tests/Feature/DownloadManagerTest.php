<?php

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Services\DownloadManager;

class DownloadManagerTest extends TestCase
{
    protected $downloadManager;

    public function setUp(): void
    {
        parent::setUp();

        // Clear any existing files in storage for testing
        Storage::fake();

        // Create an instance of DownloadManager for testing
        $this->downloadManager = new DownloadManager();
    }

    public function testDownloadManagerInitialization()
    {
        // Ensure that required directories are created during initialization
        $this->assertTrue(Storage::exists($this->downloadManager->getBlackholePathWeb()));
        $this->assertTrue(Storage::exists($this->downloadManager->getDownloadsPathWeb()));
    }

    public function testAddMagnet()
    {
        $magnetUrl = 'magnet:?xt=urn:btih:abcdef&dn=myfile&tr=http://tracker.com';

        // Test a valid magnet URL
        $result = $this->downloadManager->addMagnet($magnetUrl);
        $this->assertTrue($result);

        // Test an invalid magnet URL
        $invalidMagnetUrl = 'http://invalidurl.com';
        $result = $this->downloadManager->addMagnet($invalidMagnetUrl);
        $this->assertFalse($result);
    }

    public function testAddTorrent()
    {
        // Create a temporary test torrent file
        $tempTorrentPath = storage_path('app/temp/test.torrent');
        file_put_contents($tempTorrentPath, '');

        // Test a valid torrent file
        $result = $this->downloadManager->addTorrent($tempTorrentPath);
        $this->assertTrue($result);

        // Test an invalid torrent file
        $invalidTorrentPath = storage_path('app/temp/invalid.txt');
        file_put_contents($invalidTorrentPath, '');

        $result = $this->downloadManager->addTorrent($invalidTorrentPath);
        $this->assertFalse($result);

        // Clean up the temporary test files
        unlink($tempTorrentPath);
        unlink($invalidTorrentPath);
    }

    public function testAddDDL()
    {
        $validDDLUrl = 'http://example.com/download/file.zip';

        // Test a valid DDL URL
        $result = $this->downloadManager->addDDL($validDDLUrl);
        $this->assertTrue($result);

        // Test an invalid DDL URL
        $invalidDDLUrl = 'invalidurl';
        $result = $this->downloadManager->addDDL($invalidDDLUrl);
        $this->assertFalse($result);
    }
}

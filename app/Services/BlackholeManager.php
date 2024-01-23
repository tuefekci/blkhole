<?php

namespace App\Services;

use Error;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BlackholeManager
{
	private string $pathWeb;

	public function __construct()
	{
		try {

			// Create necessary directories based on configuration
			Storage::makeDirectory( config('blkhole.paths.blackhole') );
	
			// Set paths for blackhole and downloads web interfaces
			$this->pathWeb = config('blkhole.paths.blackhole') . DIRECTORY_SEPARATOR . "webinterface" . DIRECTORY_SEPARATOR;
	
			// Ensure the existence of specific directories
			Storage::makeDirectory($this->pathWeb);
	
		} catch (\Exception $e) {
			// Log any errors that occur during initialization
			Log::error("Error in constructor: " . $e->getMessage());
		}
	}
	
	public function addMagnet($magnetUrl) {
		try {
			// Check if the magnet URL starts with 'magnet:'
			if (\strpos($magnetUrl, 'magnet:') === false) {
				throw new \Exception("Invalid magnet URL: $magnetUrl");
			}
	
			$magnetRaw = $magnetUrl;
	
			// If the magnet URL contains percent-encoded characters, decode them
			if(preg_match('~%[0-9A-F]{2}~i', $magnetRaw)) {
				$magnetRaw = urldecode($magnetRaw);
			}
	
			// Extract hash, filename, and trackers from the magnet URL
			if (!preg_match('#magnet:\?xt=urn:btih:(?<hash>.*?)&dn=(?<filename>.*?)&tr=(?<trackers>.*?)$#', $magnetRaw, $magnet)) {
				throw new \Exception("Invalid magnet URL format: $magnetUrl");
			}
	
			// Check if filename is present and is a string
			if(!empty($magnet['filename']) && is_string($magnet['filename'])) {
				// Save the magnet URL to storage with the filename
				Storage::put($this->pathWeb . $magnet['filename'] . ".magnet", $magnetRaw);
				return true;
			} else {
				// Throw exception if filename is missing or not a string
				throw new \Exception("Missing or invalid filename in magnet URL: $magnetUrl");
			}
		} catch (\Exception $e) {
			// Log the error and return false
			Log::error("Error adding magnet URL: " . $e->getMessage());
			return false;
		}
	}

	public function addTorrent($torrentPath, $torrentName) {

		// Define the allowed file extensions for torrent files
		$allowedExtensions = ['torrent'];

		try {
			// Check if the file exists
			if (!Storage::exists($torrentPath)) {
				throw new \Exception("Torrent file not found: $torrentPath");
			}
	
			// Check if the file has a valid MIME type for a torrent file
			$mimeType = Storage::mimeType($torrentPath);
			if ($mimeType !== 'application/x-bittorrent') {
				throw new \Exception("Invalid torrent file format: $torrentPath");
			}

			// Extract the file extension using pathinfo
			$fileInfo = pathinfo($torrentPath);

			// Check if the file extension is in the list of allowed extensions
			if (!in_array(strtolower($fileInfo['extension']), $allowedExtensions)) {
				throw new \Exception("Invalid torrent file format: $torrentPath");
			}

			$nameInfo = pathinfo($torrentName);

			// Additional checks or actions can be added here if needed
			// ...
	
			// If all checks pass, you can proceed with your logic
			Storage::move($torrentPath, $this->pathWeb . $nameInfo['filename'] . "." .$fileInfo['extension']);
			return true;
	
		} catch (\Exception $e) {
			// Log the error and return false
			Log::error("Error adding torrent file: " . $e->getMessage());
			return false;
		}
	}

	public function addDDL($ddlUrl) {
		try {
			// Validate the URL format
			if (!filter_var($ddlUrl, FILTER_VALIDATE_URL)) {
				throw new \Exception("Invalid URL format: $ddlUrl");
			}
	
			// Check if the URL starts with 'http://' or 'https://'
			if (!preg_match('/^https?:\/\//', $ddlUrl)) {
				throw new \Exception("URL must start with 'http://' or 'https://': $ddlUrl");
			}
	
			// Additional checks or actions can be added here if needed
			// ...
	
			// If all checks pass, you can proceed with your logic
			Storage::put($this->pathWeb . sha1($ddlUrl).".ddl", $ddlUrl);
			return true;
	
		} catch (\Exception $e) {
			// Log the error and return false
			Log::error("Error adding DDL URL: " . $e->getMessage());
			return false;
		}
	}
	
	
}
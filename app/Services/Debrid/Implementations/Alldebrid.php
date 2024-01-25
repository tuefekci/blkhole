<?php

namespace App\Services\Debrid\Implementations;

use App\Models\Setting;
use App\Services\Debrid\Contracts\DebridServiceInterface;
use Illuminate\Support\Facades\Log;

class Alldebrid implements DebridServiceInterface
{
    // ...

	private $alldebrid;

    public function __construct() {
	
		$this->alldebrid = new \Alldebrid\Alldebrid("github.com/tuefekci/blkhole", Setting::get('account_0_token'));
		$this->alldebrid->setErrorMode('exception');
    }

    public function addTorrent($torrentPath) {
		try {
			$response = $this->alldebrid->magnetUploadFile($torrentPath);
			return $response[0];
		} catch (\Throwable $th) {
			//throw $th;
			Log::error($th->getMessage());
		}

		return false;
    }

	public function addMagnet($magnet) {
		try {
			$response = $this->alldebrid->magnetUpload($magnet);
			return $response[0];
		} catch (\Throwable $th) {
			//throw $th;
			Log::error($th->getMessage());
		}

		return false;
    }

	public function addDDL($url) {

		$result = $this->getDownload($url);

		if(!$result) {
			return false;
		}

		return [
			"magnet" => $url,
			"hash" => "",
			"name" => $result['filename'],
			"filename_original" => "",
			"size" => $result['filesize'],
			"ready" => true,
			"id" => $result['id']
		];
    }

	public function getDownload($url) {
		try {
			$link = $this->alldebrid->link($url);

			if($link->isSupported()) {
				$response = $link->unlock();
				return $response['data'];
			} else {
				return false;
			}
		} catch (\Throwable $th) {
			//throw $th;
			Log::error($th->getMessage());
		}

		return false;
	}

	public function getStatus($id=null) {

		try {
			if(!$id) {
				$response = $this->alldebrid->magnetStatus();
			} else {
				$response = $this->alldebrid->magnetStatus($id);
			}

			//$magnetID = $response[0]['id']

		} catch (\Throwable $th) {
			//throw $th;
			Log::error($th->getMessage());
		}

		return false;
	}

	public function getUserStatus() {
		try {
			$user = $this->alldebrid->user();
			if($user['isPremium']) {
				return [
					'status' => true,
					'message' => "Username: " . $user['username'] . " | " . __('Subscription: Active | Until: ') . date(DATE_RFC2822, $user['premiumUntil'])
				];
			} else {
				return [
					'status' => true,
					'message' => "Username: " . $user['username'] . " " . __('subscription is inactive.')
				];
			}
		} catch (\Throwable $th) {
			//throw $th;
		}

		return [
			'status' => false,
			'message' => __('Connection Error or Api Key invalid.')
		];
	}

	public function delete($id) {
		try {
			$response = $this->alldebrid->magnetDelete($id);
			return true;
		} catch (\Throwable $th) {
			//throw $th;
			Log::error($th->getMessage());
		}

		return false;
	}

	public function restart($id) {
		try {
			$response = $this->alldebrid->magnetRestart($id);
			return true;
		} catch (\Throwable $th) {
			//throw $th;
			Log::error($th->getMessage());
		}

		return false;
	}

}
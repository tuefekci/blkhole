<?php

namespace App\Services\Debrid\Implementations;

use App\Models\Setting;
use App\Services\Debrid\Contracts\DebridServiceInterface;
use Error;
use Exception;
use Illuminate\Support\Facades\Log;

class Alldebrid implements DebridServiceInterface
{
    // ...

	private $alldebrid;

    public function __construct() {
	
		$this->alldebrid = new \Alldebrid\Alldebrid("github.com/tuefekci/blkhole", Setting::get('account_0_token'));
		$this->alldebrid->setErrorMode('exception');
    }

	public function getProviderName() {
		return 'alldebrid';
	}

	public function add($type, $content) {
		switch ($type) {
			case 'torrent':
				return $this->addTorrent($content);
				break;
			case 'magnet':
				return $this->addMagnet($content);
				break;
			case 'ddl':
				return $this->addDDL($content);
				break;
			default:
				break;
		}

		Log::error("Alldebrid->add Error: Unknown Type!");
		return false;
	}

    public function addTorrent($torrent) {
		try {
			$response = $this->alldebrid->magnetUploadFile($torrent, 'inline');
			return $response;
		} catch (\Throwable $th) {
			Log::error("alldebrid->addTorrent: " . $th->getMessage());
			throw $th;
		}
    }

	public function addMagnet($magnet) {
		try {
			$response = $this->alldebrid->magnetUpload($magnet);
			return $response;
		} catch (\Throwable $th) {
			Log::error("alldebrid->addMagnet: " . $th->getMessage());
			throw $th;
		}
    }

	public function addDDL($url) {

		try {
			$result = $this->getDownload($url);

			return [
				"magnet" => $url,
				"hash" => "",
				"name" => $result['filename'],
				"filename_original" => "",
				"size" => $result['filesize'],
				"ready" => true,
				"id" => $result['id']
			];
		} catch (\Throwable $th) {
			Log::error("alldebrid->addddl: " . $th->getMessage());
			throw $th;
		}
    }

	public function getDownload($url) {
		try {
			if($this->alldebrid->linkIsSupported($url)) {
				$response = $this->alldebrid->linkUnlock($url);
				return $response;
			} else {
				Log::error("alldebrid->getDownload: !isSupported");
			}
		} catch (\Throwable $th) {
			Log::error("alldebrid->getDownload: " . $th->getMessage());
			throw $th;
		}

		return false;
	}

	private function getTypeFromStatusCode($statusCode) {
		switch ($statusCode) {
			case 0:
			case 1:
			case 2:
			case 3:
				return 'processing';
			case 4:
				return 'ready';
			case 5:
			case 6:
			case 7:
			case 8:
			case 9:
			case 10:
			case 11:
				return 'error';
			default:
				return 'error';
		}
	}

	public function getStatus($id=null) {
		try {
			if (empty($id)) {
				$response = $this->alldebrid->magnetStatus();
			} else {
				$response = $this->alldebrid->magnetStatus($id);

				if (empty($response['id']) || (string) $response['id'] !== (string) $id) {
					$response = [];
				} else {
					$response = [$response];
				}
			}

			$return = [];
			foreach ($response as $item) {

				$links = [];

				foreach ($item['links'] as $link) {
					$unlockedLink = $this->alldebrid->linkUnlock($link['link']);
					$link['link'] = $unlockedLink['link'];
					$links[] = $link;
				}

				try {
					$return[] = [
						"id" => $item['id'],
						"name" => $item['filename'],
						"size" => $item['size'],
						"hash" => $item['hash'],
						"debridStatusMessage" => $item['status'],
						"debridStatusCode" => $item['statusCode'],
						"status" => $this->getTypeFromStatusCode($item['statusCode']),
	
						"links" => $links
					];
				} catch (\Throwable $th) {
					Log::error("alldebrid->getStatus response loop error: " . $th->getMessage());
				}
			}

			if(!empty($id) && !empty($return)) {
				return $return[0];
			}

			return $return;

		} catch (\Throwable $th) {
			//throw $th;
			Log::error("alldebrid->getStatus: " . $th->getMessage());
		}

		return [];
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
			Log::error("alldebrid->delete: " . $th->getMessage());
			throw $th;
		}
	}

	public function restart($id) {
		try {
			$response = $this->alldebrid->magnetRestart($id);
			return true;
		} catch (\Throwable $th) {
			Log::error("alldebrid->restart: " . $th->getMessage());
			throw $th;
		}
	}

}
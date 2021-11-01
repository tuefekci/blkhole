<?php
require_once(__DIR__ . '/vendor/autoload.php');

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

use Amp\Parallel\Worker;



Loop::run(function () {

	\Amp\Loop::repeat($msInterval = 100, function ($watcherId) {
		echo "Heartbeat".PHP_EOL;
	});


		$urls = [
			'https://secure.php.net',
			'https://amphp.org',
			'https://github.com',			
		];

		$promises = [];
		foreach ($urls as $url) {
			$promises[$url] = \Amp\call(function() use ($url) {
				$deferred = new Deferred();

				\Amp\Loop::delay(3 * 1000, function () use ($url, $deferred) {
					$deferred->resolve($url);
				});

				return $deferred->promise();
			});
		}

		$responses = yield Promise\all($promises);

		foreach ($responses as $url => $response) {
			\printf("Read %d bytes from %s\n", \strlen($response), $url);
		}


});
<?php


// DebridServiceFactory.php

namespace App\Services;

use App\Services\Debrid\Implementations\Alldebrid;

class DebridServiceFactory
{

	private function getAvailableDebridService() {

		return "Alldebrid";

        // Add decision-making logic here
        if ($criteria === 'SomeCriteria') {
            return 'ServiceA';
        } elseif ($criteria === 'AnotherCriteria') {
            return 'ServiceB';
        }
	}

    public static function createDebridService($serviceIdentifier="alldebrid")
    {
		//$serviceIdentifier = $this->getAvailableDebridService();

		$serviceIdentifier = strtolower($serviceIdentifier);

        if ($serviceIdentifier === 'alldebrid') {
            return new Alldebrid();
        }

        // Handle other cases or throw an exception if needed
        throw new \Exception('Invalid Debrid service identifier!');
    }
}
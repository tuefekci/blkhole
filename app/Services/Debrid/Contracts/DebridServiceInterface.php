<?php

// DebridServiceInterface.php
namespace App\Services\Debrid\Contracts;

interface DebridServiceInterface
{
	public function getProviderName();
    public function getUserStatus();
	public function getStatus($id = null);
	public function add($type, $content);
    public function delete($id);
    public function restart($id);
}

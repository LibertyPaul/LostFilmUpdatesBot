<?php

namespace TelegramAPI;

require_once(__DIR__.'/VelocityController.php');
require_once(__DIR__.'/../lib/KeyValueStorage/MemcachedStorage.php');

class VelocityControllerFactory{

	public static function getMemcachedBasedController($keyPrefix, $expirationSeconds = 0){
		$memcachedStorage = new \MemcachedStorage($keyPrefix, $expirationSeconds);
		$controller = new VelocityController($memcachedStorage);

		return $controller;
	}

}

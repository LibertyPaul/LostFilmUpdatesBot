<?php

namespace Stuff;

function createMemcached(){
	static $memcached;

	if(isset($memcached) === false){
		$memcached = new \Memcached();
		
		$res = $memcached->addServer('localhost', 11211);
		if($res === false){
			unset($memcached);
			throw new \RuntimeException('memcached connection error');
		}
	}

	return $memcached;
}

<?php

namespace Stuff;

function createMemcache(){
	static $memcache;

	if(isset($memcache) === false){
		$memcache = new \Memcache;
		$res = $memcache->connect('localhost', 11211);
		if($res === false){
			unset($memcache);
			throw new \RuntimeException('memcache connection error');
		}
	}
	return $memcache;
}


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

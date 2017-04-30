<?php

namespace Stuff;

function createMemcache(){
	static $memcache;

	if(isset($memcache) === false){
		$memcache = new \Memcache;
		$res = $memcache->connect('localhost', 11211);
		if($res === false){
			unset($memcache);
			throw new \RuntimeException('memcached connect error');
		}
	}
	return $memcache;
}


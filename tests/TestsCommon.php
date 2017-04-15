<?php

namespace TestsCommon;

function keyExists($filePath, $key){
	$hFile = fopen($filePath, 'r');
	if($hFile === false){
		return false;
	}

	while($line = fgets($hFile)){
		if(strpos($line, $key) !== false){
			return true;
		}
	}

	return false;
}

function generateRandomString($size){
	$bytes = openssl_random_pseudo_bytes($size);
	$key = bin2hex($bytes);
	return $key;
}

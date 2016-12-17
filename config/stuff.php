<?php
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/../Exceptions/StdoutTextException.php');

function createPDO(){
	static $pdo;
	if(isset($pdo) === false){
		$dsn = 'mysql:dbname='.DBname.';host='.HOST;
		$pdo = new PDO($dsn, DBuser, DBpass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$pdo->exec('set names utf8');
	}
	return $pdo;
}
	

function createMemcache(){
	static $memcache;
	if(isset($memcache) === false){
		$memcache = new Memcache;
		$res = $memcache->connect('localhost', 11211);
		if($res === false){
			unset($memcache);
			throw new Exception('memcached connect error');
		}
	}
	return $memcache;
}



function createOrOpenLogFile($path){
	if(is_file($path)){
		$hf = fopen($path, 'a');
		if($hf === false)
			throw new Exception('log fopen error');
	}
	else{
		$hf = fopen($path, 'a');
		if($hf === false)
			throw new Exception("log fopen '$path' O_CREAT error");
		$res = chmod($path, 0777);
		if($res === false)
			throw new Exception("chmod error");
	}
	return $hf;
}
		



function findMatchingParenthesis($str, $parenthesisPos){
	if($parenthesisPos < 0 || $parenthesisPos >= strlen($str)){
		throw new StdoutTextException('$parenthesisPos is out of $str');
	}

	$step = null;
	$opening = null;
	$closing = null;
	
	switch($str[$parenthesisPos]){
	case '(':
	case ')':
		$opening = '(';
		$closing = ')';
		break;
			
	case '[':
	case ']':
		$opening = '[';
		$closing = ']';
		break;
			
	case '{':
	case '}':
		$opening = '{';
		$closing = '}';
		break;
	
	case '<':
	case '>':
		$opening = '<';
		$closing = '>';
		break;
	
	default:
		throw new StdoutTextException('Unknown parenthesis ('.$str[$parenthesisPos].')');
	}
	
	if($str[$parenthesisPos] === $opening){
		$step = 1;
	}
	else if($str[$parenthesisPos] === $closing){
		$step = -1;
	}
	else{
		throw new StdoutTextException('Given parenthesis does not match');
	}
	
	
	$parenthesisBalance = $step;
	$pos = $parenthesisPos + $step;
	while($pos >= 0 && $pos < strlen($str)){
		switch($str[$pos]){
		case $opening:
			++$parenthesisBalance;
			break;
	
		case $closing:
			--$parenthesisBalance;
			break;
		}
		
		if($parenthesisBalance === 0){
			break;
		}
		
		$pos += $step;
	}
	
	if($parenthesisBalance === 0){
		return $pos;
	}

	return false;
}


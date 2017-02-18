<?php
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/../Exceptions/StdoutTextException.php');

function createPDO(){
	static $pdo;
	if(isset($pdo) === false){
		$dsn = 'mysql:dbname='.DBname.';host='.HOST.';charset=utf8mb4';
		$pdo = new PDO($dsn, DBuser, DBpass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
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


function findMatchingParenthesis($str, $parenthesisPos){
	$length = strlen($str);

	if($parenthesisPos < 0 || $parenthesisPos >= $length){
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
	else{
		$step = -1;
	}
	
	
	$depth = 0; // how deep we are: (  1  (  2  (  3  )  2  (  3  (  4  )  3  )  2  )  1  )
	$pos = $parenthesisPos;
	while($pos >= 0 && $pos < $length){
		switch($str[$pos]){
			case $opening:
				$depth += $step;
				break;
	
			case $closing:
				$depth -= $step;
				break;
		}
		
		if($depth === 0){
			return $pos;
		}
		
		$pos += $step;
	}

	return false;
}


<?php
require_once("config.php");

function createSQL(){
	static $sql;
	if(isset($sql) === false){
		$sql = new mysqli(HOST, DBuser, DBpass, DBname);
		if(!$sql){
			unset($sql);
			throw new Exception("SQL object creating error");
		}
		if($sql->connect_error){
			$errorText = $sql->connect_error; 
			unset($sql);
			throw new Exception($errorText);
		}
		$res = $sql->set_charset("utf8");
		if($res === false){
			unset($sql);
			throw new Exception("MySQLi set_charset error");
		}			
	}
	return $sql;
}

function createPDO(){
	static $pdo;
	if(isset($pdo) === false){
		$dsn = 'mysql:dbname='.DBname.';host='.HOST;
		$pdo = new PDO($dsn, DBuser, DBpass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$pdo->exec("set names utf8");
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
			throw new Exception("memcached connect error");
		}
	}
	return $memcache;
}


function createOrOpenLogFile($path){
	if(is_file($path)){
		$hf = fopen($path, 'a');
		if($hf === false)
			throw new Exception("log fopen error");
	}
	else{
		$hf = fopen($path, 'a');
		if($hf === false)
			throw new Exception("log fopen O_CREAT error");
		$res = chmod($path, 0777);
		if($res === false)
			throw new Exception("chmod error");
	}
	return $hf;
}
		

















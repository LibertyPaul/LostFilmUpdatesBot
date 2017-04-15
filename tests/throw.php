<?php

require_once(__DIR__.'/../ExceptionHandler.php');

if(isset($argv[1]) === false){
	echo "Usage $argv[0] <key>\n";
	exit;
}

throw new Exception("Test exception. Key=$argv[1]");

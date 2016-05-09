<?php
require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__))."/ShowParser.php");
require_once(realpath(dirname(__FILE__))."/SeriesParser.php");
require_once(realpath(dirname(__FILE__))."/config/cron_actions.php");


function exception_handler($exception){
	echo '[EXCEPTION]'.$exception->getMessage().PHP_EOL;

	$path = realpath(dirname(__FILE__)).'/logs/ParsersErrorLog.txt';
	$errorLogFile = createOrOpenLogFile($path);	
	$res = fwrite($errorLogFile, $exception->getMessage());
	if($res === false){
		exit("error log write error");
	}
	$res = fclose($errorLogFile);
	if($res === false){
		exit("error log close error");
	}
}

set_exception_handler('exception_handler');

function error_handler($errno, $errstr, $errfile, $errline, $errcontext){
	throw new Exception("[ERROR] `$errstr'\n$errfile:$errline\n", $errno);
}

set_error_handler('error_handler');


const showListURL = "https://www.lostfilm.tv/serials.php";
$showsParser = new ShowParser('CP1251');
$showsParser->loadSrc(showListURL);
$showsParser->run();

const rssURL = "http://www.lostfilm.tv/rssdd.xml";
$seriesParser = new SeriesParser();
$seriesParser->loadSrc(rssURL);
$seriesParser->run();






<?php
require_once(__DIR__.'/ShowParser.php');
require_once(__DIR__.'/HTTPRequester.php');

const showListURL = "https://www.lostfilm.tv/serials.php";

$requester = new HTTPRequester();
$showParser = new ShowParser($requester, 'CP1251');
try{
	$showParser->loadSrc(showListURL);
}
catch(HTTPException $ex){
	$date = date('Y.m.d H:i:s');
	echo "[HTTP ERROR]\t$date\t".basename(__FILE__).':'.__LINE__."\t".showListURL."\t".$ex->getMessage().PHP_EOL;
}

$showParser->run();


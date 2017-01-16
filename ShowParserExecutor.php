<?php
require_once(__DIR__.'/ShowParser.php');
require_once(__DIR__.'/HTTPRequester.php');
require_once(__DIR__.'/EchoTracer.php');

const showListURL = "https://www.lostfilm.tv/serials.php";

$tracer = new EchoTracer('ShowParserExecutor');

$requester = new HTTPRequester();
$showParser = new ShowParser($requester, 'CP1251');
try{
	$showParser->loadSrc(showListURL);
}
catch(HTTPException $ex){
	$tracer->logException('[HTTP ERROR]', $ex);
}

$showParser->run();


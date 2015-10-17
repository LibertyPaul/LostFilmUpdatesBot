<?php

require_once(realpath(dirname(__FILE__))."/ShowParser.php");
require_once(realpath(dirname(__FILE__))."/SeriesParser.php");
require_once(realpath(dirname(__FILE__))."/config/cron_actions.php");

function error_handler($errno, $errstr, $errfile, $errline, $errcontext){
	$path = realpath(dirname(__FILE__)).'/logs/ParsersErrorLog.txt';
	$errorLogFile = fopen($path, 'a');
	if($errorLogFile === false)
		exit("errorLigFile fopen error");
	$res = chmod($path, 0777);
	if($res === false)
		throw new StdoutTextException("chmod error");
	
	
	$errorText = "$errno $errstr $errfile:$errline";
	fwrite($errorLogFile, $errorText);
	fclose($errorLogFile);
}

set_error_handler('error_handler');


const showListURL = "https://www.lostfilm.tv/serials.php";
const seriesListURL = "https://www.lostfilm.tv/browse.php?o=";

$seriesParserStartPage = 0;
const seriesParserPageStep = 15;


$showsParser = new ShowParser('CP1251');
$showsParser->loadSrc(showListURL);
$showsParser->run();

$seriesParser = new SeriesParser('CP1251');
do{
	$seriesParser->loadSrc(seriesListURL.$seriesParserStartPage);
	$alreadyParsed = $seriesParser->run();
	echo "$seriesParserStartPage : $alreadyParsed\n";

	$seriesParserStartPage += seriesParserPageStep;
}while($alreadyParsed !== seriesParserPageStep && $seriesParserStartPage < 6465);






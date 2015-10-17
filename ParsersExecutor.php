<?php
require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__))."/ShowParser.php");
require_once(realpath(dirname(__FILE__))."/SeriesParser.php");
require_once(realpath(dirname(__FILE__))."/config/cron_actions.php");

function error_handler($errno, $errstr, $errfile, $errline, $errcontext){
	$path = realpath(dirname(__FILE__)).'/logs/ParsersErrorLog.txt';
	$errorLogFile = createOrOpenLogFile($path);	
	$errorText = "$errno $errstr $errfile:$errline";
	$res = fwrite($errorLogFile, $errorText);
	if($res === false)
		exit("error log write error");
	$res = fclose($errorLogFile);
	if($res === false)
		exit("error log close error");
	
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






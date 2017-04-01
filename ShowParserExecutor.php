<?php
require_once(__DIR__.'/ErrorHandler.php');
require_once(__DIR__.'/ExceptionHandler.php');

require_once(__DIR__.'/ShowParser.php');
require_once(__DIR__.'/HTTPRequester.php');

$requester = new HTTPRequester();
$showParser = new ShowParser($requester);
$showParser->run();


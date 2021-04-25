<?php

namespace TelegramAPI;

ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once(__DIR__.'/../../../lib/ErrorHandler.php');
require_once(__DIR__.'/../../../lib/ExceptionHandler.php');

require_once(__DIR__.'/../../../core/BotPDO.php');
require_once(__DIR__.'/../../../lib/Config.php');
require_once(__DIR__.'/../../UpdateHandler.php');
require_once(__DIR__.'/../../Webhook.php');

require_once(__DIR__.'/../../../lib/Tracer/TracerFactory.php');


$pdo = \BotPDO::getInstance();
$tracer = \TracerFactory::getTracer(__NAMESPACE__.'DebugWebhook', $pdo);
$tracer->logEvent(__FILE__, __LINE__, 'Debug Webhook was started');


$tracer->logDebug(__FILE__, __LINE__, 'Fetching POST data...');
$messagePath = __DIR__.'/update.json';
if(count($argv) > 1){
	$messagePath = $argv[1];
}

$updateJSON = file_get_contents($messagePath);
if($updateJSON === false){
    $tracer->logfError(
        __FILE__, __LINE__,
        '[%s] was not found', $messagePath
    );  

    exit;
}
$tracer->logfDebug(__FILE__, __LINE__, 'Fetched [%d] bytes.', strlen($updateJSON));


$tracer->logDebug(__FILE__, __LINE__, 'Creating Config object...');
$config = \Config::getConfig($pdo);
$tracer->logDebug(__FILE__, __LINE__, 'Config was created.');


$password = $config->getValue('TelegramAPI', 'Webhook Password');
$tracer->logDebug(__FILE__, __LINE__, 'The Webhook Password is *******.');


$tracer->logDebug(__FILE__, __LINE__, 'Creating UpdateHandler object...');
$updateHandler = new UpdateHandler();
$tracer->logDebug(__FILE__, __LINE__, 'UpdateHandler was created...');


$tracer->logDebug(__FILE__, __LINE__, 'Creating Webhook object...');
$webhook = new Webhook($updateHandler);
$tracer->logDebug(__FILE__, __LINE__, 'Webhook was created...');


$tracer->logDebug(__FILE__, __LINE__, 'Processing Update...');
$webhook->processUpdate($password, $updateJSON);
$tracer->logDebug(__FILE__, __LINE__, 'Processing has finished.');

$tracer->logEvent(__FILE__, __LINE__, PHP_EOL . $updateJSON);

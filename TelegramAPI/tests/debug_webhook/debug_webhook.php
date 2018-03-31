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

require_once(__DIR__.'/../../../lib/Tracer/Tracer.php');

$tracer = new \Tracer(__NAMESPACE__.'DebugWebhook');
$tracer->logEvent('[DEBUG]', __FILE__, __LINE__, 'Debug Webhook was started');

$updateJSON = file_get_contents(__DIR__.'/update.json');
if($updateJSON === false){
    $tracer->logError(
        '[o]', __FILE__, __LINE__,
        'update.json was not found'
    );  

    exit;
}


$config = new \Config(\BotPDO::getInstance());
$password = $config->getValue('TelegramAPI', 'Webhook Password');

$updateHandler = new UpdateHandler();

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

$tracer->logEvent('[MESSAGE SENT]', __FILE__, __LINE__, PHP_EOL.$updateJSON);

<?php

namespace TelegramAPI;

require_once(__DIR__.'/../../../lib/ErrorHandler.php');
require_once(__DIR__.'/../../../lib/ExceptionHandler.php');

require_once(__DIR__.'/../../UpdateHandler.php');
require_once(__DIR__.'/../../Webhook.php');

require_once(__DIR__.'/../../../lib/Tracer/Tracer.php');
require_once(__DIR__.'/input_debug_webhook.php');

$tracer = new \Tracer('DebugWebhook');

$updateJSON = $update_json;
assert($updateJSON !== false);

$updateHandler = new UpdateHandler();

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

$tracer->logEvent('[MESSAGE SENT]', __FILE__, __LINE__, PHP_EOL.$updateJSON);

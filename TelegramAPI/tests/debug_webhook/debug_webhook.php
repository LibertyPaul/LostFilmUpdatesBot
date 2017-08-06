<?php

namespace TelegramAPI;

require_once(__DIR__.'/../../../lib/ErrorHandler.php');
require_once(__DIR__.'/../../../lib/ExceptionHandler.php');

require_once(__DIR__.'/../../TelegramAPI.php');
require_once(__DIR__.'/../../UpdateHandler.php');
require_once(__DIR__.'/../../Webhook.php');
require_once(__DIR__.'/../../../core/BotPDO.php');
require_once(__DIR__.'/../../../lib/HTTPRequester/HTTPRequesterFactory.php');

require_once(__DIR__.'/../../../lib/Tracer/Tracer.php');
require_once(__DIR__.'/input_debug_webhook.php');

$tracer = new \Tracer('DebugWebhook');

$pdo = \BotPDO::getInstance();

$config = new \Config($pdo);
$password = $config->getValue('Webhook', 'Password');

$updateJSON = $update_json;
assert($updateJSON !== false);

$HTTPRequesterFactory = new \HTTPRequesterFactory($pdo);
$HTTPRequester = $HTTPRequesterFactory->getInstance();

$config = new \Config(\BotPDO::getInstance());
$botToken = $config->getValue('TelegramAPI', 'token');
assert($botToken !== null);

$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);

$updateHandler = new UpdateHandler($telegramAPI);

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

$tracer->logEvent('[MESSAGE SENT]', __FILE__, __LINE__, PHP_EOL.$updateJSON);

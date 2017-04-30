<?php
require_once(__DIR__.'/../../ErrorHandler.php');
require_once(__DIR__.'/../../ExceptionHandler.php');

require_once(__DIR__.'/../../TelegramAPI.php');
require_once(__DIR__.'/../../UpdateHandler.php');
require_once(__DIR__.'/../../webhook/Webhook.php');
<<<<<<< HEAD
=======
require_once(__DIR__.'/../../BotPDO.php');
>>>>>>> master
require_once(__DIR__.'/../../HTTPRequesterFactory.php');

require_once(__DIR__.'/../../Tracer.php');
require_once(__DIR__.'/input_debug_webhook.php');

$tracer = new Tracer('DebugWebhook');

<<<<<<< HEAD
$HTTPRequester = HTTPRequesterFactory::getInstance();
$telegramAPI = new TelegramAPI($HTTPRequester);
$updateHandler = new UpdateHandler($telegramAPI);
$webhook = new Webhook($updateHandler);
=======
$config = new Config(BotPDO::getInstance());
$password = $config->getValue('Webhook', 'Password');
>>>>>>> master

$updateJSON = $update_json;
assert($updateJSON !== false);

$HTTPRequesterFactory = new HTTPRequesterFactory();
$HTTPRequester = $HTTPRequesterFactory->getInstance();

$config = new Config(BotPDO::getInstance());
$botToken = $config->getValue('TelegramAPI', 'token');
assert($botToken !== null);

$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);

$updateHandler = new UpdateHandler($telegramAPI);

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

$tracer->logEvent('[MESSAGE SENT]', __FILE__, __LINE__, PHP_EOL.$updateJSON);

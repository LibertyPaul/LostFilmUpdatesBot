<?php
require_once(__DIR__.'/../../ErrorHandler.php');
require_once(__DIR__.'/../../ExceptionHandler.php');

require_once(__DIR__.'/../../TelegramAPI.php');
require_once(__DIR__.'/../../UpdateHandler.php');
require_once(__DIR__.'/../../webhook/Webhook.php');
require_once(__DIR__.'/../../BotPDO.php');
require_once(__DIR__.'/../../HTTPRequesterFactory.php');

require_once(__DIR__.'/input_debug_webhook.php');

$config = new Config(BotPDO::getInstance());
$password = $config->getValue('Webhook', 'Password');

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

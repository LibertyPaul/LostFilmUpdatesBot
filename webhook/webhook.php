<?php
require_once(__DIR__.'/../ErrorHandler.php');
require_once(__DIR__.'/../ExceptionHandler.php');

require_once(__DIR__.'/../TelegramAPI.php');
require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/Webhook.php');
require_once(__DIR__.'/../BotPDO.php');
require_once(__DIR__.'/../HTTPRequesterFactory.php');

$password = isset($_GET['password']) ? $_GET['password'] : null;
$updateJSON = file_get_contents('php://input');
assert($updateJSON !== false);

$HTTPRequester = HTTPRequesterFactory::getInstance();

$config = new Config(BotPDO::getInstance());
$botToken = $config->getValue('TelegramAPI', 'token');
assert($botToken !== null);

$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);

$updateHandler = new UpdateHandler($telegramAPI);

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

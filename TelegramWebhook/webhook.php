<?php
require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/../core/TelegramAPI.php');
require_once(__DIR__.'/../core/UpdateHandler.php');
require_once(__DIR__.'/Webhook.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterFactory.php');

$password = isset($_GET['password']) ? $_GET['password'] : null;
$updateJSON = file_get_contents('php://input');
assert($updateJSON !== false);

$pdo = BotPDO::getInstance();

$HTTPRequesterFactory = new HTTPRequesterFactory($pdo);
$HTTPRequester = $HTTPRequesterFactory->getInstance();

$config = new Config($pdo);
$botToken = $config->getValue('TelegramAPI', 'token');
assert($botToken !== null);

$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);

$updateHandler = new UpdateHandler($telegramAPI);

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

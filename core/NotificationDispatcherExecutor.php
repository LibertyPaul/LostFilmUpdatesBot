<?php
require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/../TelegramAPI/TelegramAPI.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/NotificationDispatcher.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterFactory.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');

$pdo = BotPDO::getInstance();
$HTTPRequesterFactory = new HTTPRequesterFactory($pdo);
$HTTPRequester = $HTTPRequesterFactory->getInstance();


$config = new Config($pdo);
$botToken = $config->getValue('TelegramAPI', 'token');
assert($botToken !== null);

$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);
$notificationGenerator = new NotificationGenerator();
$dispatcher = new NotificationDispatcher($notificationGenerator, $telegramAPI);
$dispatcher->run();

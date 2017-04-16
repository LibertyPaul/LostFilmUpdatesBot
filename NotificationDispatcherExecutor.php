<?php
require_once(__DIR__.'/ErrorHandler.php');
require_once(__DIR__.'/ExceptionHandler.php');

require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/NotificationDispatcher.php');
require_once(__DIR__.'/HTTPRequesterFactory.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/config/Config.php');

$HTTPRequester = HTTPRequesterFactory::getInstance();

$config = new Config(BotPDO::getInstance());
$botToken = $config->getValue('TelegramAPI', 'token');
assert($botToken !== null);

$telegramAPI = new TelegramAPI($botToken, $HTTPRequester);
$notificationGenerator = new NotificationGenerator();
$dispatcher = new NotificationDispatcher($notificationGenerator, $telegramAPI);
$dispatcher->run();

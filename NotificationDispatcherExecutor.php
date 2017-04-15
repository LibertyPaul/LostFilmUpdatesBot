<?php
require_once(__DIR__.'/ErrorHandler.php');
require_once(__DIR__.'/ExceptionHandler.php');

require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/NotificationDispatcher.php');
require_once(__DIR__.'/HTTPRequester.php');

$HTTPRequester = new HTTPRequester();
$telegramAPI = new TelegramAPI($HTTPRequester);
$notificationGenerator = new NotificationGenerator();
$dispatcher = new NotificationDispatcher($notificationGenerator, $telegramAPI);
$dispatcher->run();

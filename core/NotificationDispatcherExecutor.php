<?php

namespace core;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/NotificationDispatcher.php');

$notificationGenerator = new NotificationGenerator();
$dispatcher = new NotificationDispatcher($notificationGenerator);
$dispatcher->run();

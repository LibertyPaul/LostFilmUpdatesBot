<?php

require_once(__DIR__.'/TelegramBotFactory.php');
require_once(__DIR__.'/Notifier.php');
require_once(__DIR__.'/NotificationDispatcher.php');


$botFactory = new TelegramBotFactory();
$notifier = new Notifier($botFactory);
$dispatcher = new NotificationDispatcher($notifier);
$dispatcher->run();

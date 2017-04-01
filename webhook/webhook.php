<?php
require_once(__DIR__.'/../ErrorHandler.php');
require_once(__DIR__.'/../ExceptionHandler.php');

require_once(__DIR__.'/../TelegramBotFactory.php');
require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/Webhook.php');

$botFactory = new TelegramBotFactory();
$updateHandler = new UpdateHandler($botFactory);
$webhook = new Webhook($updateHandler);

$password = isset($_GET['password']) ? $_GET['password'] : null;
$updateJSON = file_get_contents('php://input');
assert($updateJSON !== false);

$webhook->processUpdate($password, $updateJSON);

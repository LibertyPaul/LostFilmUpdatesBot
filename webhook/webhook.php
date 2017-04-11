<?php
require_once(__DIR__.'/../ErrorHandler.php');
require_once(__DIR__.'/../ExceptionHandler.php');

require_once(__DIR__.'/../TelegramAPI.php');
require_once(__DIR__.'/../UpdateHandler.php');
require_once(__DIR__.'/Webhook.php');
require_once(__DIR__.'/../HTTPRequester.php');

$password = isset($_GET['password']) ? $_GET['password'] : null;
$updateJSON = file_get_contents('php://input');
assert($updateJSON !== false);

$HTTPRequester = new HTTPRequester();
$telegramAPI = new TelegramAPI($HTTPRequester);
$updateHandler = new UpdateHandler($telegramAPI);
$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

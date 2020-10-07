<?php

namespace TelegramAPI;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/UpdateHandler.php');
require_once(__DIR__.'/Webhook.php');
require_once(__DIR__.'/../core/BotPDO.php');

$password = isset($_GET['password']) ? $_GET['password'] : null;
$updateJSON = file_get_contents('php://input');
assert($updateJSON !== false);

$updateHandler = new UpdateHandler();

$webhook = new Webhook($updateHandler);
$webhook->processUpdate($password, $updateJSON);

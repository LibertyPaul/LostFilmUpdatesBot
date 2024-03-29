<?php

namespace core;

require_once(__DIR__.'/MessageRouter.php');
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../TelegramAPI/MessageSender.php');
require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequesterFactory.php');

class MessageRouterFactory{

    private static function createTelegramAPISender(){
		$pdo = \BotPDO::getInstance();
		$config = \Config::getConfig($pdo);
		$botToken = $config->getValue('TelegramAPI', 'token');
		if($botToken === null){
			throw new \RuntimeException('[TelegramAPI][token] value does not exist');
		}

		$requesterFactory = new \HTTPRequester\HTTPRequesterFactory($config, $pdo);
		$requester = $requesterFactory->getInstance();

		$telegramAPI = new \TelegramAPI\TelegramAPI($botToken, $requester, $pdo);
		$telegramAPISender = new \TelegramAPI\MessageSender($telegramAPI);
		
		if($telegramAPISender instanceof MessageSenderInterface === false){
			throw new \LogicException(
				'\TelegramAPI\MessageSender is not '.
				'an instance of MessageSenderInterface.'
			);
		}

		return $telegramAPISender;
	}

	public static function getInstance(): MessageRouter {
		if(isset($instance) === false){
			$telegramAPISender = self::createTelegramAPISender();

			$messageSenders = array(
				'TelegramAPI' => $telegramAPISender
			);
			$instance = new MessageRouter($messageSenders);
		}
		return $instance;
	}
}

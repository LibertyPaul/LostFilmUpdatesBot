<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/MessageRoute.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');
require_once(__DIR__.'/../TelegramAPI/MessageSender.php');

class MessageRouter{
	private $messageSenders; # array('APIName' => MessageSender)
	private $tracer;

	# Queries
	private $getUserAPIQuery;
	
	public function __construct($messageSenders){
		$this->tracer = new \Tracer(__CLASS__);

		$this->messageSenders = array();

		assert(is_array($messageSenders));
		foreach($messageSenders as $APIName => $messageSender){
			if($messageSender instanceof MessageSenderInterface === false){
				$this->tracer->logError(
					'[ROUTER]', __FILE__, __LINE__,
					"Sender to '$APIName' is not of valid type: ".
					PHP_EOL.print_r($messageSender, true)
				);
				continue;
			}

			$this->messageSenders[$APIName] = $messageSender;
		}


		$pdo = \BotPDO::getInstance();
		$this->getUserAPIQuery = $pdo->prepare('
			SELECT `API`
			FROM `users`
			WHERE `id` = :user_id
		');
	}

	private function getUserAPI($user_id){
		$this->getUserAPIQuery->execute(
			array(
				':user_id' => $user_id
			)
		);

		$user = $this->getUserAPIQuery->fetch();

		if($user === false){
			$this->tracer->logError(
				'[NOT FOUND]', __FILE__, __LINE__,
				"User ($user_id) wasn't found"
			);
			throw new \RuntimeException("User ($user_id) wasn't found");
		}

		return $user['API'];
	}

	private function getMessageSender($API){
		if(array_key_exists($API, $this->messageSenders) === false){
			$this->tracer->logError(
				'[DATA]', __FILE__, __LINE__,
				"Unknown API '$API'".PHP_EOL.
				'Available APIs:'.PHP_EOL.
				print_r($this->messageSenders, true)
			);
			
			throw new \RuntimeException("Unknown API '$API'");
		}

		return $this->messageSenders[$API];
	}
		

	public function route($user_id){
		$userAPI = $this->getUserAPI($user_id);
		$messageSender = $this->getMessageSender($userAPI);

		$route = new MessageRoute($messageSender, $user_id);

		$this->tracer->logEvent(
			'[o]', __FILE__, __LINE__,
			"Successfully routed message to user=[$user_id]: API=[$userAPI]"
		);

		return $route;
	}
}
		




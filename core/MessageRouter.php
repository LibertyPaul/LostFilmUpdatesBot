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
	private $getUserQuery;
	
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
		$this->getUserQuery = $pdo->prepare('
			SELECT `API`, `APIIdentifier`
			FROM `users`
			WHERE `id` = :user_id
		');
	}

	public function route($user_id){
		try{
			$this->getUserQuery->execute(
				array(
					':user_id' => $user_id
				)
			);
		}
		catch(\PDOException $ex){
			$this->tracer->logException('[PDO]', __FILE__, __LINE__, $ex);
			$this->tracer->logException(
				'[PDO]', __FILE__, __LINE__,
				PHP_EOL.print_r($message, true)
			);
			throw $ex;
		}

		$user = $this->getUserQuery->fetch();
		if($user === false){
			$this->tracer->logError(
				'[NOT FOUND]', __FILE__, __LINE__,
				"User ($user_id) wasn't found"
			);
			throw new \RuntimeException("User ($user_id) wasn't found");
		}

		if(array_key_exists($user['API'], $this->messageSenders) === false){
			$this->tracer->logError(
				'[DATA]', __FILE__, __LINE__,
				"Unknown API '$user[API]'".PHP_EOL.
				'Available APIs:'.PHP_EOL.
				print_r($this->messageSenders, true)
			);
			
			throw new \RuntimeException("Unknown API '$user[API]'");
		}

		return new MessageRoute(
			$this->messageSenders[$user['API']],
			intval($user['APIIdentifier'])
		);
	}
}
		




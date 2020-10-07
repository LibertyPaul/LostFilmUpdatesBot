<?php

namespace TelegramAPI;

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../core/UpdateHandler.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');


abstract class WebhookReasons{
	const OK				= 0;
	const invalidPassword	= 1;
	const formatError		= 2;
	const failed			= 3;
	const duplicateUpdate	= 4;
	const correctButIgnored	= 5;
}

class Webhook{
	private $incomingMessagesTracer;
	private $tracer;
	private $updateHandler;

	private $selfWebhookPassword;

	public function __construct(UpdateHandler $updateHandler){
		$this->updateHandler = $updateHandler;

		$pdo = \BotPDO::getInstance();

		try{
			$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
			$this->incomingMessagesTracer = \TracerFactory::getTracer(
				__NAMESPACE__.'.IncomingData',
				null,
				true,
				false
			);
		}
		catch(\Throwable $ex){
			\TracerCompiled::syslogCritical(
				'[TRACER]', __FILE__, __LINE__,
				'Unable to create Tracer instance'
			);
		}

		$config = \Config::getConfig($pdo);
		$this->selfWebhookPassword = $config->getValue('TelegramAPI', 'Webhook Password');
	}

	private function verifyPassword(string $password){
		if($this->selfWebhookPassword === null){
			$this->tracer->logNotice(
				'[SECURITY]', __FILE__, __LINE__,
				'Webhook password is not set. Check was skipped.'
			);
			
			return true;
		}

		return $password === $this->selfWebhookPassword;
	}

	private function respondFinal(int $reason){
		switch($reason){
			case WebhookReasons::OK:
				$HTTPCode = 200;
				$text = 'Accepted. Processed.';
				break;

			case WebhookReasons::invalidPassword:
				$HTTPCode = 401;
				$text = 'Invalid password. Try 123456.';
				break;

			case WebhookReasons::formatError:
				$HTTPCode = 400;
				$text = 'Format error.';
				break;

			case WebhookReasons::failed:
				$HTTPCode = 200;
				$text = 'Failed for some reason.';
				break;

			case WebhookReasons::duplicateUpdate:
				$HTTPCode = 208;
				$text = 'It is a duplicate. Piss off.';
				break;

			case WebhookReasons::correctButIgnored:
				$HTTPCode = 200;
				$text = 'Correct but ignored.';
				break;

			default:
				$this->tracer->logfError(
					'[UNKNOWN REASON]', __FILE__, __LINE__,
					'Failed with unknown reason: [%d].',
					$reason
				);

				$text = 'hmm...';
				$HTTPCode = 200;
		}

		http_response_code($HTTPCode);
		echo $text.PHP_EOL;

		$this->tracer->logEvent(
			'[RESPONSE]', __FILE__, __LINE__,
			"HTTPCode=[$HTTPCode] Text=[$text]"
		);
	}

	private function logUpdate($update){
		$prettyJSON = json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if($prettyJSON === false){
			$this->tracer->logError(
				'[JSON]', __FILE__, __LINE__,
				'json_encode error: '.json_last_error_msg()
			);

			$this->tracer->logNotice(
				'[INFO]', __FILE__, __LINE__,
				PHP_EOL.print_r($update, true)
			);
			return;
		}

		$this->incomingMessagesTracer->logEvent(
			'[INCOMING MESSAGE]', __FILE__, __LINE__,
			PHP_EOL.$prettyJSON
		);
	}

	private static function validateFields($update){
		return
			isset($update)								&&
			isset($update->message)						&&
			isset($update->message->from)				&&
			isset($update->message->from->id)			&&
			isset($update->message->chat)				&&
			isset($update->message->chat->id)			&&
			(
				isset($update->message->text)	||
				isset($update->message->voice)	||
				isset($update->message->migrate_from_chat_id)
			);
	}

	public function processUpdate(string $password, string $postData){
		if($this->verifyPassword($password) === false){
			$this->tracer->logNotice(
				'[SECURITY]', __FILE__, __LINE__,
				"Incorrect password: '$password'".PHP_EOL.
				$postData
			);
			$this->respondFinal(WebhookReasons::invalidPassword);
			return;
		}
		
		$update = json_decode($postData);
		if($update === null){
			$this->tracer->logfError(
				'[JSON]', __FILE__, __LINE__,
				"Unable to parse JSON update: [%s]",
				json_last_error_msg()
			);

			$this->tracer->logfDebug(
				'[o]', __FILE__, __LINE__,
				'Raw JSON: [%s]',
				$postData
			);

			$this->respondFinal(WebhookReasons::formatError);
			return;
		}

		$this->logUpdate($update);

		if(self::validateFields($update) === false){
			$this->tracer->logNotice(
				'[o]', __FILE__, __LINE__,
				'Update is not supported:'.PHP_EOL.
				print_r($update, true)
			);

			$this->respondFinal(WebhookReasons::correctButIgnored);
			return;
		}

		try{
			$this->updateHandler->handleUpdate($update);
			$this->respondFinal(WebhookReasons::OK);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[UPDATE HANDLER]', __FILE__, __LINE__, $ex);
			$this->respondFinal(WebhookReasons::failed);
		}
	}
}

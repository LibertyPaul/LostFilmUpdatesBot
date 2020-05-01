<?php

namespace TelegramAPI;

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../core/UpdateHandler.php');
require_once(__DIR__.'/../lib/Tracer/Tracer.php');

require_once(__DIR__.'/../lib/HTTPRequester/HTTPRequester.php');


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

	private $messageResendEnabled = false;
	private $messageResendURL = null;

	private $forwardingChat;
	private $forwardingSilent;
	private $forwardEverything;

	private $telegramAPI;

	public function __construct(UpdateHandler $updateHandler){
		assert($updateHandler !== null);
		$this->updateHandler = $updateHandler;

		try{
			$this->tracer = new \Tracer(__CLASS__);
			$this->incomingMessagesTracer = new \Tracer(__NAMESPACE__.'.IncomingData');
		}
		catch(\Throwable $ex){
			TracerBase::syslogCritical(
				'[TRACER]', __FILE__, __LINE__,
				'Unable to create Tracer instance'
			);
		}

		$config = new \Config(\BotPDO::getInstance());
		$this->selfWebhookPassword = $config->getValue('TelegramAPI', 'Webhook Password');

		$this->messageResendEnabled = $config->getValue('TelegramAPI', 'Message Resend Enabled');
		if($this->messageResendEnabled === 'Y'){
			$this->messageResendURL = $config->getValue('TelegramAPI', 'Message Resend URL');
			if($this->messageResendURL === null){
				$this->tracer->logWarning(
					'[o]', __FILE__, __LINE__,
					'Message resend is enabled, but no URL is set'
				);

				$this->messageResendEnabled = 'N';
			}
		}

		$this->forwardingChat = $config->getValue(
			'TelegramAPI',
			'Forwarding Chat'
		);

		$this->forwardingSilent = $config->getValue(
			'TelegramAPI',
			'Forwarding Silent',
			'Y'
		) === 'Y';

		$this->forwardEverything = $config->getValue(
			'TelegramAPI',
			'Forward Everything',
			'N'
		) === 'Y';

		$telegramAPIToken = $config->getValue('TelegramAPI', 'token');
		try{
			$HTTPrequesterFactory = new \HTTPRequester\HTTPRequesterFactory($config);
			$HTTPRequester = $HTTPrequesterFactory->getInstance();
			$this->telegramAPI = new TelegramAPI($telegramAPIToken, $HTTPRequester);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			$this->telegramAPI = null;
		}
	}

	private function verifyPassword($password){
		if($this->selfWebhookPassword === null){
			$this->tracer->logNotice(
				'[SECURITY]', __FILE__, __LINE__,
				'Webhook password is not set. Check was skipped.'
			);
			
			return true;
		}

		return $password === $this->selfWebhookPassword;
	}

	private function respondFinal($reason){
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
				$this->tracer->logError('[UNKNOWN REASON]', __FILE__, __LINE__, $reason);
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
			isset($update)						&&
			isset($update->message)				&&
			isset($update->message->from)		&&
			isset($update->message->from->id)	&&
			isset($update->message->chat)		&&
			isset($update->message->chat->id)	&&
			(
				isset($update->message->text) ||
				isset($update->message->voice)
			);
	}

	private static function shouldBeForwarded($message){
		return
			isset($message->audio)		||
			isset($message->document)	||
			isset($message->game)		||
			isset($message->photo)		||
			isset($message->sticker)	||
			isset($message->video)		||
			isset($message->video_note)	||
			isset($message->contact)	||
			isset($message->location)	||
			isset($message->venue);
	}

	public function processUpdate($password, $postData){
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
				"Unable to parse JSON update: [%s]\n",
				'Raw JSON: [%s]',
				json_last_error_msg(),
				$postData
			);

			$this->respondFinal(WebhookReasons::formatError);
			return;
		}

		$this->logUpdate($update);
		
		if($this->forwardEverything	|| self::shouldBeForwarded($update->message)){
			$this->tracer->logDebug(
				'[ATTACHMENT FORWARDING]', __FILE__, __LINE__,
				'Message is eligible for forwarding.'
			);

			if(
				$this->forwardingChat !== null	&&
				isset($update->message)			&& # HotFix
				$this->telegramAPI !== null
			){
				try{
					$this->telegramAPI->forwardMessage(
						$this->forwardingChat,
						$update->message->chat->id,
						$update->message->message_id,
						$this->forwardingSilent
					);
				}
				catch(\Throwable $ex){
					$this->tracer->logException(
						'[ATTACHMENT FORWARDING]', __FILE__, __LINE__, 
						$ex
					);
				}
			}
			else{
				$this->tracer->logfWarning(
					'[o]', __FILE__, __LINE__,
					'Unable to forward due to:'					.PHP_EOL.
					'	$this->forwardingChat !== null:	[%d]'	.PHP_EOL.
					'	$this->telegramAPI !== null:	[%d]'	.PHP_EOL.
					'	isset($update->message):		[%d]'	.PHP_EOL,
					$this->forwardingChat !== null,
					$this->telegramAPI !== null,
					isset($update->message)
				);
			}
		}

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
		catch(\core\DuplicateUpdateException $ex){
			$this->respondFinal(WebhookReasons::duplicateUpdate);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[UPDATE HANDLER]', __FILE__, __LINE__, $ex);
			$this->respondFinal(WebhookReasons::failed);
		}
	}
}

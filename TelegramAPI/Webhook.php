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

	private $attachmentForwardingChat;
	private $attachmentForwardingSilent;

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

		$this->attachmentForwardingChat = $config->getValue(
			'TelegramAPI',
			'Attachment Forwarding Chat'
		);

		$res = $config->getValue(
			'TelegramAPI',
			'Attachment Forwarding Silent'
		);

		if($res === 'Y'){
			$this->attachmentForwardingSilent = true;
		}
		else{
			$this->attachmentForwardingSilent = false;
		}

		$telegramAPIToken = $config->getValue('TelegramAPI', 'token');
		try{
			$HTTPrequesterFactory = new \HTTPRequesterFactory($config);
			$HTTPRequester = $HTTPrequesterFactory->getInstance();
			$this->telegramAPI = new TelegramAPI($telegramAPIToken, $HTTPRequester);
		}
		catch(\Throwable $ex){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				'Unable to create Telegram API'
			);

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
				http_response_code(200);
				echo 'Accepted. Processed.'.PHP_EOL;
				break;

			case WebhookReasons::invalidPassword:
				http_response_code(401);
				echo 'Invalid password. Try 123456.'.PHP_EOL;
				break;

			case WebhookReasons::formatError:
				http_response_code(400);
				echo 'Format error.'.PHP_EOL;
				break;

			case WebhookReasons::failed:
				http_response_code(200);
				echo 'Failed for some reason.'.PHP_EOL;
				break;

			case WebhookReasons::duplicateUpdate:
				http_response_code(208);
				echo 'It is a duplicate. Piss off.'.PHP_EOL;
				break;

			case WebhookReasons::correctButIgnored:
				http_response_code(200);
				echo 'Correct but ignored.'.PHP_EOL;
				break;

			default:
				$this->tracer->logError('[UNKNOWN REASON]', __FILE__, __LINE__, $reason);
				echo 'hmm...'.PHP_EOL;
				http_response_code(200);
		}
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
			$this->tracer->logError(
				'[JSON]', __FILE__, __LINE__,
				'Unable to parse JSON update: '.json_last_error_msg().PHP_EOL.
				'Raw JSON:'.PHP_EOL.
				$postData
			);

			$this->respondFinal(WebhookReasons::formatError);
			return;
		}

		$this->logUpdate($update);

		if(
			$this->attachmentForwardingChat !== null	&&
			isset($update->message)						&& # HotFix
			self::shouldBeForwarded($update->message)	&&
			$this->telegramAPI !== null
		){
			$this->tracer->logDebug(
				'[ATTACHMENT FORWARDING]', __FILE__, __LINE__,
				'Message is eligible for forwarding.'
			);

			try{
				$this->telegramAPI->forwardMessage(
					$this->attachmentForwardingChat,
					$update->message->chat->id,
					$update->message->message_id,
					$this->attachmentForwardingSilent
				);
			}
			catch(\Throwable $ex){
				$this->tracer->logException(
					'[ATTACHMENT FORWARDING]', __FILE__, __LINE__, 
					$ex
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
				
		if($this->messageResendEnabled === 'Y'){
			try{
				$this->resendUpdate($postData, $this->messageResendURL);
			}
			catch(\Throwable $ex){
				$this->tracer->logError(
					'[MESSAGE STREAM]', __FILE__, __LINE__,
					'Message resend has failed: URL=['.$this->messageResendURL.']'.PHP_EOL.
					'postData:'.PHP_EOL.print_r($postData, true)
				);
				$this->tracer->logException('[MESSAGE STREAM]', __FILE__, __LINE__, $ex);
			}
		}
	}

	private function resendUpdate($postData, $URL){
		$testStream = new HTTPRequester();

		$res = $testStream->sendJSONRequest($URL, $postData);
		if($res['code'] >= 400){
			throw new \RuntimeException('Message resend has failed with code '.$res['code']);
		}
	}
}

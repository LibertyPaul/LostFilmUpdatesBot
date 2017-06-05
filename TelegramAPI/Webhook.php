<?php
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
	private $incomingLog;
	private $tracer;
	private $updateHandler;

	private $selfWebhookPassword;

	public function __construct(UpdateHandler $updateHandler){
		assert($updateHandler !== null);
		$this->updateHandler = $updateHandler;

		try{
			$this->tracer = new Tracer(__CLASS__);
			$this->incomingLog = new Tracer('incomingMessages');
		}
		catch(Exception $ex){
			TracerBase::syslogCritical('[TRACER]', __FILE__, __LINE__, 'Unable to create Tracer instance');
		}

		$config = new Config(BotPDO::getInstance());
		$this->selfWebhookPassword = $config->getValue('Webhook', 'Password');
	}

	private function verifyPassword($password){
		if($this->selfWebhookPassword === null){
			$this->tracer->logNotice('[SECURITY]', __FILE__, __LINE__, 'Webhook password is not set. Check was skipped.');
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
				http_response_code(500);
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
			$this->tracer->logError('[JSON]', __FILE__, __LINE__, 'json_encode error: '.json_last_error_msg());
			$this->tracer->logNotice('[INFO]', __FILE__, __LINE__, PHP_EOL.print_r($update, true));
			return;
		}

		$this->incomingLog->logEvent('[INCOMING MESSAGE]', __FILE__, __LINE__, PHP_EOL.$prettyJSON);
	}

	public function processUpdate($password, $postData){
		if($this->verifyPassword($password) === false){
			$this->tracer->logNotice('[SECURITY]', __FILE__, __LINE__, "Incorrect password: '$password'");
			$this->tracer->logNotice('[INFO]', __FILE__, __LINE__, PHP_EOL.$postData);
			$this->respondFinal(WebhookReasons::invalidPassword);
			return;
		}
		
		$update = json_decode($postData);
		if($update === null){
			$this->tracer->logError('[JSON]', __FILE__, __LINE__, 'json_decode error: '.json_last_error_msg());
			$this->tracer->logNotice('[INFO]', __FILE__, __LINE__, PHP_EOL."'$postData'");
			$this->respondFinal(WebhookReasons::formatError);
			return;
		}

		$this->logUpdate($update);

		if(isset($update->message->text) === false){
			$this->tracer->logNotice('[INFO]', __FILE__, __LINE__, 'Ignored message:'.PHP_EOL);
			$this->tracer->logNotice('[INFO]', __FILE__, __LINE__, PHP_EOL.print_r($update, true));
			$this->respondFinal(WebhookReasons::correctButIgnored);
			return;
		}

		try{
			$this->updateHandler->handleUpdate($update);
			$this->respondFinal(WebhookReasons::OK);
		}
		catch(DuplicateUpdateException $ex){
			$this->respondFinal(WebhookReasons::duplicateUpdate);
		}
		catch(Exception $ex){
			$this->tracer->logException('[UPDATE HANDLER]', __FILE__, __LINE__, $ex);
			$this->respondFinal(WebhookReasons::failed);
		}
				
		if(defined('MESSAGE_STREAM_URL')){
			try{
				$this->resendUpdate($postData, MESSAGE_STREAM_URL);
			}
			catch(Exception $ex){
				$this->tracer->logException('[MESSAGE STREAM]', __FILE__, __LINE__, $ex);
			}
		}
	}

	private function resendUpdate($postData, $URL){
		$testStream = new HTTPRequester();

		if(defined('MESSAGE_STREAM_PASSWORD')){
			$URL .= '?password='.MESSAGE_STREAM_PASSWORD;
		}
		
		$res = $testStream->sendJSONRequest($URL, $postData);
		if($res['code'] >= 400){
			$this->tracer->logError('[STREAM]', __FILE__, __LINE__, 'message resend has failed with code '.$res['code']);
		}
	}
}

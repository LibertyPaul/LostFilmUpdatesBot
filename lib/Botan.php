<?php

require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/Config.php');
require_once(__DIR__.'/HTTPRequester/HTTPRequesterFactory.php');

/**
 * Class Botan
 * @package YourProject
 *
 * Usage:
 *
 * private $token = 'token';
 *
 * public function _incomingMessage($message_json) {
 *	 $messageObj = json_decode($message_json, true);
 *	 $messageData = $messageObj['message'];
 *
 *	 $botan = new YourProject\Botan($this->token);
 *	 $botan->track($messageData, 'Start');
 * }
 *
 */

class Botan {

	/**
	 * @var string Tracker url
	 */
	protected $template_uri = 'https://api.botan.io/track?token=#TOKEN&uid=#UID&name=#NAME';

	/**
	 * @var string Yandex AppMetrica application api_key
	 */
	protected $token;

	private $HTTPRequester;

	function __construct($token) {
		if (empty($token) || !is_string($token)) {
			throw new \Exception('Token should be a string', 2);
		}
		$this->token = $token;

		$pdo = \BotPDO::getInstance();
		$config = new \Config($pdo);
		$HTTPRequesterFactory = new \HTTPRequesterFactory($config);
		$this->HTTPRequester = $HTTPRequesterFactory->getInstance();
	}

	public function track($message, $event_name = 'Message') {
		$uid = $message['from']['id'];
		$url = str_replace(
			['#TOKEN', '#UID', '#NAME'],
			[$this->token, urlencode($uid), urlencode($event_name)],
			$this->template_uri
		);
		$result = $this->request($url, $message);
		if ($result['error']) {
			throw new Exception('Error Processing Request', 1);
		}
		
	}

	protected function request($url, $body) {
		$res = $this->HTTPRequester->sendJSONRequest($url, json_encode($body));

		return [
			'error' => $res['code'] >= 400,
			'response' => json_decode($res['value'], true)
		];
	}

	private function curlRequest($url, $body) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json'
			],
			CURLOPT_POSTFIELDS => json_encode($body)
		]);
		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}

	private function streamContextRequest($url, $body) {
		$options = array(
			'http' => array(
				'header'  => 'Content-Type: application/json',
				'method'  => 'POST',
				'content' => json_encode($body)
			)
		);
		$context = stream_context_create($options);
		$response = file_get_contents($url, false, $context);

		return $response;
	}
}

<?php

require_once(__DIR__.'/../SpeechRecognizer.php');
require_once(__DIR__.'/../../Config.php');
require_once(__DIR__.'/../../../core/BotPDO.php');
require_once(__DIR__.'/../../HTTPRequester/HTTPRequester.php');

class SpeechRecognizerTest extends PHPUnit_Framework_TestCase{

	public function testRecognizer(){
		$pdo = \BotPDO::getInstance();
		$config = \Config::getConfig($pdo);
		$HTTPRequester = new \HTTPRequester\HTTPRequester();
		$recognizer = new \SpeechRecognizer\SpeechRecognizer($config, $HTTPRequester);


		$audioBinary = file_get_contents(__DIR__.'/StarGate.ogg');
		$audioBase64 = base64_encode($audioBinary);

		$result = $recognizer->recognize($audioBase64, 'ogg');

		$starGateFound = false;

		foreach($result as $transcript => $confidence){
			$transcriptLC = strtolower($transcript);
			if(
				strpos($transcriptLC, 'звездные') !== -1 &&
				strpos($transcriptLC, 'врата') !== -1
			){
				$starGateFound = true;
			}
		}

		$this->assertTrue($starGateFound);
	}

}








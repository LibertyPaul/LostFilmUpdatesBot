<?php

namespace SpeechRecognizer;

require_once(__DIR__.'/../Tracer/Tracer.php');
require_once(__DIR__.'/../Config.php');
require_once(__DIR__.'/../HTTPRequester/HTTPRequesterInterface.php');

abstract class Result{
	const Success			= 0;
	const NoAPIKey			= -1;
	const APIError			= -2;
	const VelocityExceeded	= -3;
}

class SpeechRecognizer{
	private $APIURL;
	private $HTTPRequester;
	private $velocityController;
	private $tracer;

	public function __construct(
		\Config $config,
		\HTTPRequester\HTTPRequesterInterface $HTTPRequester
	){
		$this->HTTPRequester = $HTTPRequester;
		$this->tracer = new \Tracer(__CLASS__);
		$APIKey = $config->getValue('SpeechRecognizer', 'API Key', '');
		$this->APIURL = "https://speech.googleapis.com/v1/speech:recognize?key=$APIKey";
	}

	public function recognize($audioBase64, $format){
		switch($format){
			case 'ogg':
				$encoding = 'OGG_OPUS';
				break;

			default:
				throw new \RuntimeException("Format $format is not supported");
		}

		$RecognitionAudio = array(
			'content' => $audioBase64
		);

		$SpeechContext = array(
			'phrases' => array('сериал')
		);

		$RecognitionConfig = array(
			'encoding'				=> $encoding,
			'sampleRateHertz'		=> 16000,
			'languageCode'			=> 'ru-RU',
			'maxAlternatives'		=> 5,
			'profanityFilter'		=> false,
			#'speechContexts'		=> $SpeechContext,
			'enableWordTimeOffsets'	=> false
		);

		$Request = array(
			'config'	=> $RecognitionConfig,
			'audio'		=> $RecognitionAudio
		);

		$requestProperties = new \HTTPRequester\HTTPRequestProperties(
			\HTTPRequester\RequestType::Post,
			\HTTPRequester\ContentType::JSON,
			$this->APIURL,
			json_encode($Request, JSON_FORCE_OBJECT)
		);

		$result = $this->HTTPRequester->request($requestProperties);

		if($result->getCode() >= 400){
			$this->tracer->logError(
				'[SPEECH RECOGNITION API]', __FILE__, __LINE__,
				'SpeechRecognition has failed. Response:'.PHP_EOL.
				strval($result)
			);

			return Result::APIError;
		}

		$recognitionResult = json_decode($result->getBody());
		if($recognitionResult === false){
			return Result::APIError;
		}

		assert(isset($recognitionResult->results));
		assert(isset($recognitionResult->results[0]));
		assert(isset($recognitionResult->results[0]->alternatives));


		$possibleVariants = array(); # array(variant => confidence)
		
		foreach($recognitionResult->results[0]->alternatives as $alternative){
			if(isset($alternative->transcript) && isset($alternative->confidence)){
				$possibleVariants[$alternative->transcript] = $alternative->confidence;
			}
		}

		return $possibleVariants;
	}
}




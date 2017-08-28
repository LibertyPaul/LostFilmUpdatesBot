<?php

namespace SpeechRecognizer;

require_once(__DIR__.'/../Tracer/Tracer.php');
require_once(__DIR__.'/../Config.php');
require_once(__DIR__.'/../HTTPRequester/HTTPRequesterInterface.php');
#require_once(__DIR__.'/VelocityController/VelocityController.php');


abstract class Result{
	const Success			= 0;
	const NoAPIKey			= -1;
	const APIError			= -2;
	const VelocityExceeded	= -3;
}

class SpeechRecognizer{
	private $APIURL;
	private $APIKey;
	private $HTTPRequester;
	private $velocityController;
	private $tracer;

	public function __construct(\Config $config, \HTTPRequesterInterface $HTTPRequester){
		assert($config !== null);
		assert($HTTPRequester !== null);

		$this->HTTPRequester = $HTTPRequester;

		$this->tracer = new \Tracer(__CLASS__);

		$this->APIURL = 'https://speech.googleapis.com/v1/speech:recognize';

		$this->APIKey = $config->getValue('SpeechRecognizer', 'API Key');
		if($this->APIKey === null){
			$this->tracer->logError(
				'[CONFIG]', __FILE__, __LINE__,
				'[SpeechRecognizer][API Key] was not set'
			);

			throw new \RuntimeException('[SpeechRecognizer][API Key] was not set');
		}

		#$this->velocityController = new \VelocityController(__CLASS__);
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
			'speechContexts'		=> $SpeechContext,
			'enableWordTimeOffsets'	=> false
		);

		$Request = array(
			'config'	=> $RecognitionConfig,
			'audio'		=> $RecognitionAudio
		);

		$JSONRequest = json_encode($Request, JSON_PRETTY_PRINT);

		$URL = sprintf('%s?key=%s', $this->APIURL, $this->APIKey);

		$result = $this->HTTPRequester->sendJSONRequest($URL, $JSONRequest);

		if($result['code'] >= 400){
			$this->tracer->logError(
				'[SPEECH RECOGNITION API]', __FILE__, __LINE__,
				"SpeechRecognition has failed with code=[$result[code]]. Response:".PHP_EOL.
				$result['value']
			);

			return Result::APIError;
		}

		$recognitionResult = json_decode($result['value']);
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



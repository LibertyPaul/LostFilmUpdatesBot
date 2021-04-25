<?php

namespace SpeechRecognizer;

require_once(__DIR__.'/../Tracer/TracerFactory.php');
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
    private $tracer;

	public function __construct(
		\Config $config,
		\HTTPRequester\HTTPRequesterInterface $HTTPRequester,
		\PDO $pdo
	){
		$this->HTTPRequester = $HTTPRequester;
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $pdo);
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

		try{
			$result = $this->HTTPRequester->request($requestProperties);
		}
		catch(\HTTPRequester\HTTPTimeoutException $ex){
			$this->tracer->logfError(
                __FILE__, __LINE__,
                'SpeechRecognition seems to be unavailable due to [%s]',
                $ex
			);

			return Result::APIError;
		}

		if($result->isError()){
			$this->tracer->logError(
                __FILE__, __LINE__,
                'SpeechRecognition has failed:' . PHP_EOL .
                $result
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




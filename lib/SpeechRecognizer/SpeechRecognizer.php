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

	public function recognize(string $audioBase64, string $format){
		$this->tracer->logfDebug(
			__FILE__, __LINE__,
			__FUNCTION__." entered. Format: [%s], audio size: [%d] chars of base64",
			$format,
			strlen($audioBase64)
		);

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
			'sampleRateHertz'		=> 48000,
			'languageCode'			=> 'ru-RU',
			'maxAlternatives'		=> 5,
			'profanityFilter'		=> false,
			#'speechContexts'		=> $SpeechContext,
			'model'					=> 'command_and_search',
			'useEnhanced'			=> true
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
		catch(\Throwable $ex){
			$this->tracer->logfError(
				__FILE__, __LINE__,
				'SpeechRecognition seems to be unavailable due to [%s]',
				$ex
			);

			return Result::APIError;
		}

		if($result->isError()){
			$this->tracer->logfError(
				__FILE__, __LINE__,
				"Speech API call has failed:\n[%s]",
				$result
			);

			return Result::APIError;
		}

		$recognitionResult = json_decode($result->getBody(), true);
		if($recognitionResult === false){
			$this->tracer->logfError(
				__file__, __line__,
				"failed to parse speechrecognition response:\n[%s]",
				$result->getBody()
			);

			return Result::APIError;
		}

		if(	empty($recognitionResult)											||
			isset($recognitionResult['results'])					=== false	||
			isset($recognitionResult['results'][0])					=== false	||
			isset($recognitionResult['results'][0]['alternatives'])	=== false
		){
			$this->tracer->logfError(
				__FILE__, __LINE__,
				"Invalid response from Speech API:\n%s",
				print_r($recognitionResult, true)
			);

			return Result::APIError;
		}

		$possibleOptions = array(); # array(variant => confidence)
		
		foreach($recognitionResult['results'][0]['alternatives'] as $pos => $alternative){
			if(!isset($alternative['transcript']) && !isset($alternative['confidence'])){
				$this->tracer->logfWarning(
					__FILE__, __LINE__,
					"An item from Speech API lacks needed data at index: [%d]:\n%s",
					$pos,
					print_r($recognitionResult['results'][0]['alternatives'], true)
				);

				continue;
			}

			$transcript = $alternative['transcript'];
			$confidence = $alternative['confidence'];

			$possibleOptions[$transcript] = $confidence;
		}

		$this->tracer->logfEvent(
			__FILE__, __LINE__,
			"Speech recognition output:\n%s",
			print_r($possibleOptions, true)
		);

		return $possibleOptions;
	}
}




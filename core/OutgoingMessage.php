<?php

namespace core;

require_once(__DIR__.'/InlineOption.php');
require_once(__DIR__.'/MarkupType.php');
require_once(__DIR__.'/MessageAPISpecificData.php');

class OutgoingMessage{
	private $text;
	private $requestAPISpecificData;
	private $markupType;
	private $enableURLExpand;
	private $responseOptions;
	private $inlineOptions;
	private $pushDisabled;

	public function __construct(
		string $text,
		?MessageAPISpecificData $requestAPISpecificData = null,
		MarkupType $markupType = null,
		bool $enableURLExpand = false,
		array $responseOptions = null,
		array $inlineOptions = null,
		bool $pushDisabled = false
	){
		$this->text = $text;

		$this->requestAPISpecificData = $requestAPISpecificData;

		# Markup Flag verification
		if($markupType === null){
			$markupType = new MarkupType(MarkupTypeEnum::NoMarkup);
		}

		$this->markupType = $markupType;

		$this->enableURLExpand = $enableURLExpand;

		# Options presence verification
		if($responseOptions !== null && $inlineOptions !== null){
			throw new \InvalidArgumentException(
				'Options Presense Verification has failed'
			);
		}
			
		# Response Options verification
		if(empty($responseOptions)){
			$this->responseOptions = null;
		}
		else{
			foreach($responseOptions as $option){
				if(is_string($option) === false){
					throw new \InvalidArgumentException(
						'Incorrect option type (string was expected): '.
						gettype($enableURLExpand)
					);
				}
			}

			$this->responseOptions = $responseOptions;
		}

		# Inline Options verification
		if(empty($inlineOptions)){
			$this->inlineOptions = null;
		}
		else{
			foreach($inlineOptions as $option){
				if($option instanceof InlineOption === false){
					throw new \InvalidArgumentException(
						'Incorrect Inline Option type (InlineOption was expected): '.
						gettype($option)
					);
				}
			}

			$this->inlineOptions = $inlineOptions;
		}

		$this->pushDisabled = $pushDisabled;
	}

	public function getText(){
		return $this->text;
	}

	public function getRequestAPISpecificData(){
		return $this->requestAPISpecificData;
	}

	public function setRequestAPISpecificData(MessageAPISpecificData $requestAPISpecificData){
		if($this->requestAPISpecificData !== null){
			throw new \LogicException("RequestAPISpecificData is already set.");
		}

		$this->requestAPISpecificData = $requestAPISpecificData;
	}

	public function markupType(){
		return $this->markupType;
	}

	public function URLExpandEnabled(){
		return $this->enableURLExpand;
	}

	public function getResponseOptions(){
		return $this->responseOptions;
	}

	public function getInlineOptions(){
		return $this->inlineOptions;
	}

	public function pushDisabled(){
		return $this->pushDisabled;
	}

	public function __toString(){
		$enableURLExpandYN = $this->URLExpandEnabled() ? 'Y' : 'N';
		$pushDisabledYN = $this->pushDisabled() ? 'Y' : 'N';

		$responseOptions = is_array($this->responseOptions) ? $this->responseOptions : array();
		$responseOptionsStr = join(', ', $responseOptions);

		$inlineOptions = is_array($this->inlineOptions) ? $this->inlineOptions : array();
		$inlineOptionsStr = join(PHP_EOL.PHP_EOL, $inlineOptions);

		$result  = '********[OutgoingMessage]**********'							.PHP_EOL;
		$result .= sprintf("markupType:	        [%s]", $this->markupType())			.PHP_EOL;
		$result .= sprintf("URLExpandEnabled:   [%s]", $enableURLExpandYN)			.PHP_EOL;
		$result .= sprintf("PushDisabled:       [%s]", $pushDisabledYN)				.PHP_EOL;
		$result .= sprintf("Text:               [%s]", $this->getText())			.PHP_EOL;
		$result .= sprintf("Response Options:   [%s]", $responseOptionsStr)			.PHP_EOL;
		$result .= "InlineOptions:"													.PHP_EOL;
		$result .= str_replace(PHP_EOL, PHP_EOL."\t\t", "\t\t".$inlineOptionsStr)	.PHP_EOL;
		$result .= strval($this->getRequestAPISpecificData())						.PHP_EOL;
		$result .= '***********************************';
		
		return $result;
	}
}


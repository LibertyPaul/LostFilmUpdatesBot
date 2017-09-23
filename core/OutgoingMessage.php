<?php

namespace core;

require_once(__DIR__.'/InlineOption.php');
require_once(__DIR__.'/MarkupType.php');

class OutgoingMessage{
	private $text;
	private $markupType;
	private $enableURLExpand;
	private $responseOptions;
	private $inlineOptions;
	private $pushDisabled;

	private $nextMessage = null;

	public function __construct(
		$text,
		MarkupType $markupType = null,
		$enableURLExpand = false,
		array $responseOptions = null,
		array $inlineOptions = null,
		$pushDisabled = false
	){
		# Text verification
		if(is_string($text)){
			$this->text = $text;
		}
		else{
			throw new \InvalidArgumentException(
				'Incorrect text type (string was expected): '
				.gettype($text)
			);
		}

		# Markup Flag verification
		if($markupType === null){
			$markupType = new MarkupType(MarkupTypeEnum::NoMarkup);
		}

		$this->markupType = $markupType;

		# URL Expand Flag verification
		if(is_bool($enableURLExpand) === false){
			throw new \InvalidArgumentException(
				'Incorrect enableURLExpand type (bool was expected): '.
				gettype($enableURLExpand)
			);
		}
		else{
			$this->enableURLExpand = $enableURLExpand;
		}

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

		if(is_bool($pushDisabled)){
			$this->pushDisabled = $pushDisabled;
		}
		else{
			throw new \InvalidArgumentException(
				'Incorrect pushDisabled type (boolean was expected): '.gettype($pushDisabled)
			);
		}
	}

	public function appendMessage(self $message){
		if($this->nextMessage === null){
			$this->nextMessage = $message;
		}
		else{
			$this->nextMessage->appendMessage($message);
		}
	}

	public function nextMessage(){
		return $this->nextMessage;
	}

	public function getText(){
		return $this->text;
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

		$result  = '***********************************'							.PHP_EOL;
		$result .= 'OutgoingMessage:'												.PHP_EOL;
		$result .= sprintf("\tmarkupType:         [%s]",	$this->markupType())	.PHP_EOL;
		$result .= sprintf("\tURLExpandEnabled:   [%s]",	$enableURLExpandYN)		.PHP_EOL;
		$result .= sprintf("\tPushDisabled:       [%s]",	$pushDisabledYN)		.PHP_EOL;
		$result .= sprintf("\tText: [%s]", 					$this->getText())		.PHP_EOL;
		$result .= sprintf("\tResponse Options:   [%s]",	$responseOptionsStr)	.PHP_EOL;
		$result .= "\tInlineOptions:"												.PHP_EOL;
		$result .= str_replace(PHP_EOL, PHP_EOL."\t\t", "\t\t".$inlineOptionsStr)	.PHP_EOL;
		$result .= '***********************************';
		
		return $result;
	}
}


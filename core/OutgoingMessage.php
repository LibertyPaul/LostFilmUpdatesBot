<?php

namespace core;

require_once(__DIR__.'/InlineOption.php');

class OutgoingMessage{
	private $text;
	private $textContainsMarkup;
	private $enableURLExpand;
	private $responseOptions;
	private $inlineOptions;

	private $nextMessage = null;

	public function __construct(
		$text,
		$textContainsMarkup = false,
		$enableURLExpand = false,
		array $responseOptions = null,
		array $inlineOptions = null
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
		if(is_bool($textContainsMarkup)){
			$this->textContainsMarkup = $textContainsMarkup;
		}
		else{
			throw new \InvalidArgumentException(
				'Incorrect textContainsMarkup type (bool was expected): '.
				gettype($textContainsMarkup)
			);
		}

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
	}

	public function appendMessage(self $message){
		if($this->nextMessage === null){
			$this->nextMessage = $message;
		}
		else{
			$this->nextMessage->append($message);
		}
	}

	public function nextMessage(){
		return $this->nextMessage;
	}

	public function getText(){
		return $this->text;
	}

	public function textContainsMarkup(){
		return $this->textContainsMarkup;
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

	public function __toString(){
		$containsMarkupYN = $this->textContainsMarkup() ? 'Y' : 'N';
		$enableURLExpandYN = $this->URLExpandEnabled() ? 'Y' : 'N';

		$responseOptions = is_array($this->responseOptions) ? $this->responseOptions : array();
		$responseOptionsStr = join(', ', $responseOptions);

		$inlineOptions = is_array($this->inlineOptions) ? $this->inlineOptions : array();
		$inlineOptionsStr = join(PHP_EOL.PHP_EOL, $inlineOptions);

		$result  = '***********************************'							.PHP_EOL;
		$result .= 'OutgoingMessage:'												.PHP_EOL;
		$result .= sprintf("\ttextContainsMarkup: [%s]", $containsMarkupYN)			.PHP_EOL;
		$result .= sprintf("\tURLExpandEnabled:   [%s]", $enableURLExpandYN)		.PHP_EOL;
		$result .= sprintf("\tText: [%s]", $this->getText())						.PHP_EOL;
		$result .= sprintf("\tResponse Options:   [%s]", $responseOptionsStr)		.PHP_EOL;
		$result .= "\tInlineOptions:"												.PHP_EOL;
		$result .= str_replace(PHP_EOL, PHP_EOL."\t\t", "\t\t".$inlineOptionsStr)	.PHP_EOL;
		$result .= '***********************************';
		
		return $result;
	}
}


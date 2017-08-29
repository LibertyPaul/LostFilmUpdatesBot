<?php

namespace core;

class OutgoingMessage{
	private $text;
	private $textContainsMarkup;
	private $enableURLExpand;
	private $responseOptions;
	private $shareBotContact;

	private $nextMessage = null;

	public function __construct(
		$text,
		$textContainsMarkup = false,
		$enableURLExpand = false,
		$responseOptions = null,
		$shareBotContact = false
	){
		if(is_string($text)){
			$this->text = $text;
		}
		else{
			throw new \InvalidArgumentException(
				'Incorrect text type (string was expected): '
				.gettype($text)
			);
		}

		if(is_bool($textContainsMarkup)){
			$this->textContainsMarkup = $textContainsMarkup;
		}
		else{
			throw new \InvalidArgumentException(
				'Incorrect textContainsMarkup type (bool was expected): '.
				gettype($textContainsMarkup)
			);
		}

		if(is_bool($enableURLExpand) === false){
			throw new \InvalidArgumentException(
				'Incorrect enableURLExpand type (bool was expected): '.
				gettype($enableURLExpand)
			);
		}
		else{
			$this->enableURLExpand = $enableURLExpand;
		}

		if($responseOptions === null){
			$this->responseOptions = null;
		}
		elseif(is_array($responseOptions) === false){
			throw new \InvalidArgumentException(
				'Incorrect responseOptions type (array was expected): '.
				gettype($enableURLExpand)
			);
		}
		elseif(empty($responseOptions)){
			throw new \InvalidArgumentException(
				'responseOptions is expected to be not empty array'
			);
		}
		elseif($shareBotContact){
			throw new \InvalidArgumentException(
				'responseOptions and shareBotContact can not be both true'
			);
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

		if(is_bool($shareBotContact) === false){
			throw new \InvalidArgumentException(
				'Incorrect shareBotContact type (boolean was expected): '.
				gettype($shareBotContact)
			);
		}
		elseif($shareBotContact && empty($responseOptions) === false){
			throw new \InvalidArgumentException(
				'responseOptions and shareBotContact can not be both true'
			);
		}
		else{
			$this->shareBotContact = $shareBotContact;
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

	public function getShareBotContact(){
		return $this->shareBotContact;
	}

	public function __toString(){
		$containsMarkupYN = $this->textContainsMarkup ? 'Y' : 'N';
		$enableURLExpandYN = $this->enableURLExpand ? 'Y' : 'N';
		$shareBotContact = $this->shareBotContact ? 'Y' : 'N';

		$result  = '***********************************'					.PHP_EOL;
		$result .= 'OutgoingMessage:'										.PHP_EOL;
		$result .= sprintf("\ttextContainsMarkup: [%s]", $containsMarkupYN)	.PHP_EOL;
		$result .= sprintf("\tURLExpandEnabled:   [%s]", $enableURLExpandYN).PHP_EOL;
		$result .= sprintf("\tshareBotContact:    [%s]", $shareBotContact)	.PHP_EOL;
		$result .= sprintf(
			"\tResponse Options:   [%s]",
			join(
				', ',
				is_array($this->responseOptions) ? $this->responseOptions : array()
			)
		)																	.PHP_EOL;
		$result .= "\tText:"												.PHP_EOL;
		$result .= str_replace(PHP_EOL, PHP_EOL."\t\t", "\t\t".$this->text)	.PHP_EOL;
		$result .= '***********************************';
		
		return $result;
	}
}


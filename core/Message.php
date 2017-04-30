<?php

class Message{
	private $fields;

	public function __construct(
		$chat_id,
		$text,
		$parse_mode = null,
		$disable_web_page_preview = null,
		$disable_notification = null,
		$reply_to_message_id = null,
		$reply_markup = null
	)
	{
		$this->fields = array();
		
		if(is_int($chat_id)){
			$this->fields['chat_id'] = $chat_id;
		}
		else{
			throw new IncorrectArgumentException('Incorrect type of $chat_id');
		}

		if(is_string($text)){
			$this->fields['text'] = $text;
		}
		else{
			throw new IncorrectArgumentException('Incorrect type of $text');
		}

		if(is_string($parse_mode)){
			$this->fields['parse_mode'] = $parse_mode;
		}
		elseif($parse_mode !== null){
			throw new IncorrectArgumentException('Incorrect type of $parse_mode');
		}

		if(is_bool($disable_web_page_preview)){
			$this->fields['disable_web_page_preview'] = $disable_web_page_preview;
		}
		elseif($disable_web_page_preview !== null){
			throw new IncorrectArgumentException('Incorrect type of $disable_web_page_preview');
		}

		if(is_bool($disable_notification)){
			$this->fields['disable_notification'] = $disable_notification;
		}
		elseif($disable_notification !== null){
			throw new IncorrectArgumentException('Incorrect type of $disable_notification');
		}

		if(is_int($reply_to_message_id)){
			$this->fields['reply_to_message_id'] = $reply_to_message_id;
		}
		elseif($reply_to_message_id !== null){
			throw new IncorrectArgumentException('Incorrect type of $reply_to_message_id');
		}

		if(is_array($reply_markup)){
			$this->fields['reply_markup'] = $reply_markup;
		}
		elseif($reply_markup !== null){
			throw new IncorrectArgumentException('Incorrect type of $reply_markup');
		}

	}

	public function get(){
		return $this->fields;
	}

	public function toPrettyJSON(){
		return json_encode($this->fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

}

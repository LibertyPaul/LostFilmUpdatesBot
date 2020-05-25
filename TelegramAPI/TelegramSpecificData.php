<?php

namespace TelegramAPI;

require_once(__DIR__.'/../core/MessageAPISpecificData.php');

class TelegramSpecificData implements \core\MessageAPISpecificData{
	private $message_id;
	private $update_id;
	private $APIErrorCode;

	public function __construct(
		int $message_id,
		int $update_id,
		int $APIErrorCode = null
	){
		$this->message_id = $message_id;
		$this->update_id = $update_id;
		$this->APIErrorCode = $APIErrorCode;
	}

	public function getUniqueMessageId(): int{
		return $this->update_id;
	}

	public function getAPIErrorCode(): ?int{
		return $this->APIErrorCode;
	}

	public function getMessageId(): int{
		return $this->message_id;
	}

	public function __toString(): string{
		if($this->APIErrorCode !== null){
			$APIErrorCodeStr = strval($this->APIErrorCode);
		}
		else{
			$APIErrorCodeStr = '<Null>';
		}

		$res =
			"---------[Telegram Specific Data]---------"	.PHP_EOL.
			"Update ID:  [$this->update_id]"			.PHP_EOL.
			"Message ID: [$this->message_id]"			.PHP_EOL.
			"API Error:  [$APIErrorCodeStr]"			.PHP_EOL.
			"-----------------------------------------";

		return $res;
	}
}

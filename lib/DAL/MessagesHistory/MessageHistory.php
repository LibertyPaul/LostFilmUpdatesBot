<?php

namespace DAL;

class MessageHistory{
	private $id;
	private $time;
	private $source;
	private $userId;
	private $externalId;
	private $text;
	private $inResponseTo;
	private $statusCode;

	public function __construct(
		?int $id,
		\DateTimeInterface $time,
		string $source,
		int $userId,
		?int $externalId,
		string $text,
		int $inResponseTo = null,
		int $statusCode = null
	){
		$this->id = $id;
		$this->time = $time;

		// TODO: implement verification
		// MessageHistorySource::verify($source);
		$this->source = $source;

		$this->userId = $userId;
		$this->externalId = $externalId;
		$this->text = substr($text, 0, 5000);
		$this->inResponseTo = $inResponseTo;
		$this->statusCode = $statusCode;
	}

	public function getId(){
		return $this->id;
	}

	public function getTime(){
		return $this->time;
	}

	public function getSource(){
		return $this->source;
	}

	public function getUserId(){
		return $this->userId;
	}

	public function getExternalId(){
		return $this->externalId;
	}

	public function getText(){
		return $this->text;
	}

	public function getInResponseTo(){
		return $this->inResponseTo;
	}

	public function getStatusCode(){
		return $this->statusCode;
	}


	public function __toString(){
		$timeStr = $this->getTime()->format('d.m.Y H:i:s');

		$result =
			'"""""""""""""""[Message History]"""""""""""""""'			.PHP_EOL.
			sprintf('Id:             [%d]', $this->getId())				.PHP_EOL.
			sprintf('Time:           [%s]', $timeStr)					.PHP_EOL.
			sprintf('Source:         [%s]', $this->getSource())			.PHP_EOL.
			sprintf('User ID:        [%d]', $this->getUserId())			.PHP_EOL.
			sprintf('External ID:    [%d]', $this->getExternalId())		.PHP_EOL.
			sprintf('In Response To: [%d]', $this->getInResponseTo())	.PHP_EOL.
			sprintf('Status Code:    [%d]', $this->getStatusCode())		.PHP_EOL.
			'Text:'														.PHP_EOL.
			$this->getText()											.PHP_EOL.
			'"""""""""""""""""""""""""""""""""""""""""""""""';

		return $result;
	}
}

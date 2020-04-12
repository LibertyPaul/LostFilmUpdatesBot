<?php

namespace DAL;

class Notification{
	private $id;
	private $seriesId;
	private $userId;
	private $responseCode;
	private $retryCount;
	private $lastDeliveryAttemptTime;

	public function __construct(
		int $id = null,
		int $seriesId,
		int $userId,
		int $responseCode = null,
		int $retryCount,
		\DateTimeInterface $lastDeliveryAttemptTime = null
	){
		$this->id = $id;
		$this->seriesId = $seriesId;
		$this->userId = $userId;
		$this->responseCode = $responseCode;
		$this->retryCount = $retryCount;
		$this->lastDeliveryAttemptTime = $lastDeliveryAttemptTime;
	}

	public function getId(){
		return $this->id;
	}

	public function setId(int $id){
		assert($this->id === null);
		$this->id = $id;
	}

	public function getSeriesId(){
		return $this->seriesId;
	}

	public function getUserId(){
		return $this->userId;
	}

	public function getResponseCode(){
		return $this->responseCode;
	}

	public function (){
		return $this->muted;
	}

	public function toggleMuted(){
		$this->muted = !$this->muted;
	}

	public function getRegistrationTime(){
		return $this->registration_time;
	}

	public function __toString(){
		$isDeletedYN = $this->isDeleted() ? 'Y' : 'N';
		$mutedYN = $this->isMuted() ? 'Y' : 'N';
		$regTime = $this->registration_time->format('d.m.Y H:i:s');

		$result =
			'+++++++++++++++[USER]++++++++++++++'				.PHP_EOL.
			sprintf('Id:                [%d]', $this->getId())	.PHP_EOL.
			sprintf('API:               [%s]', $this->getAPI())	.PHP_EOL.
			sprintf('Is Deleted?:       [%s]', $isDeletedYN)	.PHP_EOL.
			sprintf('Muted?:            [%s]', $mutedYN)		.PHP_EOL.
			sprintf('Registration Date: [%s]', $regTime)		.PHP_EOL.
			'+++++++++++++++++++++++++++++++++++';

		return $result;
	}
}

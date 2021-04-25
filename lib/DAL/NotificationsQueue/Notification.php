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
		?int $id,
		int $seriesId,
		int $userId,
		?int $responseCode,
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

	public function getRetryCount(){
		return $this->retryCount;
	}

	# TODO: alter HTTPCode column to internal format
	public function applyDeliveryResult(int $HTTPCode){
		$this->responseCode = $HTTPCode;
		$this->retryCount += 1;
		$this->lastDeliveryAttemptTime = new \DateTimeImmutable();
	}

	public function getLastDeliveryAttemptTime(){
		return $this->lastDeliveryAttemptTime;
	}


	public function __toString(){
		if($this->getLastDeliveryAttemptTime() !== null){
			$LDATime = $this->getLastDeliveryAttemptTime()->format('d.m.Y H:i:s');
		}
		else{
			$LDATime = '<null>';
		}

		$result =
			'===========[NOTIFICATION]=========='									.PHP_EOL.
			sprintf('ID:                         [%d]', $this->getId())				.PHP_EOL.
			sprintf('Series ID:                  [%d]', $this->getSeriesId())		.PHP_EOL.
			sprintf('User ID:                    [%d]', $this->getUserId())			.PHP_EOL.
			sprintf('Response Code:              [%d]', $this->getResponseCode())	.PHP_EOL.
			sprintf('Retry Count:                [%d]', $this->getRetryCount())		.PHP_EOL.
			sprintf('Last Delivery Attempt Time: [%s]', $LDATime)					.PHP_EOL.
			'===================================';

		return $result;
	}
}

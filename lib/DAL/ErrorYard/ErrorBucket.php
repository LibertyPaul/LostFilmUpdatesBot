<?php

namespace DAL;

class ErrorBucket{
	private $id;
	private $firstAppearanceTime;
	private $lastAppearanceTime;
	private $count;
	private $errorId;

	public function __construct(
		int $id = null,
		\DateTimeInterface $firstAppearanceTime,
		\DateTimeInterface $lastAppearanceTime,
		int $count,
		int $errorId
	){
		$this->id = $id;
		$this->firstAppearanceTime = $firstAppearanceTime;
		$this->lastAppearanceTime = $lastAppearanceTime;
		$this->count = $count;
		$this->errorId = $errorId;
	}

	public function getId(){
		return $this->id;
	}

	public function setId(int $id){
		assert($this->id === null);
		$this->id = $id;
	}

	public function getFirstAppearanceTime(){
		return $this->firstAppearanceTime;
	}

	public function getLastAppearanceTime(){
		return $this->lastAppearanceTime;
	}

	public function getCount(){
		return $this->count;
	}

	public function getErrorId(){
		return $this->errorId;
	}

	public function __toString(){
		$FATime = $this->getFirstAppearanceTime()->format('d.m.Y H:i:s');
		$LATime = $this->getLastAppearanceTime()->format('d.m.Y H:i:s');

		$result =
			'+++++++++++++++++++[Error Bucket]++++++++++++++++++'	.PHP_EOL.
			sprintf('Id:                [%d]', $this->getId())		.PHP_EOL.
			sprintf('First Appearance:  [%s]', $FATime)				.PHP_EOL.
			sprintf('Last Appearance:   [%s]', $LATime)				.PHP_EOL.
			sprintf('Count:             [%d]', $this->getCount())	.PHP_EOL.
			sprintf('Error Id:          [%d]', $this->getErrorId())	.PHP_EOL.
			'+++++++++++++++++++++++++++++++++++++++++++++++++++';

		return $result;
	}
}

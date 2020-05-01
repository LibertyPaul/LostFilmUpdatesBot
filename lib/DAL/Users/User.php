<?php

namespace DAL;

class User{
	private $id;
	private $API;
	private $isDeleted;
	private $muted;
	private $registration_time;
	private $justRegistred;

	public function __construct(
		int $id = null,
		string $API,
		bool $isDeleted,
		bool $muted,
		\DateTimeInterface $registration_time
	){
		$this->id = $id;
		$this->API = $API;
		$this->isDeleted = $isDeleted;
		$this->muted = $muted;
		$this->registration_time = $registration_time;
		$this->justRegistred = false;
	}

	public function getId(){
		return $this->id;
	}

	public function setId(int $id){
		assert($this->id === null);
		$this->id = $id;
	}

	public function getAPI(){
		return $this->API;
	}

	public function isDeleted(){
		return $this->isDeleted;
	}

	public function markDeleted(){
		$this->isDeleted = true;
	}

	public function isMuted(){
		return $this->muted;
	}

	public function toggleMuted(){
		$this->muted = !$this->muted;
	}

	public function getRegistrationTime(){
		return $this->registration_time;
	}

	public function setJustRegistred(){
		$this->justRegistred = true;
	}

	public function isJustRegistred(){
		return $this->justRegistred;
	}

	public function __toString(){
		$isDeletedYN = $this->isDeleted() ? 'Y' : 'N';
		$mutedYN = $this->isMuted() ? 'Y' : 'N';
		$justRegistredYN = $this->isJustRegistred() ? 'Y' : 'N';
		$regTime = $this->registration_time->format('d.m.Y H:i:s');

		$result =
			'+++++++++++++++++++[USER]++++++++++++++++++'		.PHP_EOL.
			sprintf('Id:                [%d]', $this->getId())	.PHP_EOL.
			sprintf('API:               [%s]', $this->getAPI())	.PHP_EOL.
			sprintf('Is Deleted?:       [%s]', $isDeletedYN)	.PHP_EOL.
			sprintf('Muted?:            [%s]', $mutedYN)		.PHP_EOL.
			sprintf('Registration Date: [%s]', $regTime)		.PHP_EOL.
			sprintf('Just Registred?:   [%s]', $justRegistredYN).PHP_EOL.
			'+++++++++++++++++++++++++++++++++++++++++++';

		return $result;
	}
}

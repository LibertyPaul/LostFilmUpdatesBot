<?php

namespace core;

abstract class SendResult{
	const MIN		= 0;

	const Success	= 0;
	const Fail		= 1;

	const MAX		= 1;

	public static function validate(int $value){
		if($value < self::MIN || $value > self::MAX){
			throw new \LogicException("Unknown SendResult value [$value].");
		}
	}

	public static function toString(int $value){
		self::validate($value);

		switch($value){
		case self::Success:
			return "Success";
			break;

		case self::Fail:
			return "Failure";
			break;
		}
	}
}

class MessageDeliveryResult{
	private $sendResult;
	private $externalId;

	public function __construct(int $sendResult, ?int $externalId){
		SendResult::validate($sendResult);

		$this->sendResult = $sendResult;
		$this->externalId = $externalId;
	}

	public function getSendResult(): int{
		return $this->sendResult;
	}

	public function getExternalId(): ?int{
		return $this->externalId;
	}


	public function __toString(): string{
		$sendResultStr = SendResult::toString($this->sendResult);

		$res  = '--------------[MessageDeliveryResult]--------------'	.PHP_EOL;
		$res .= sprintf("Send Result: [%s]", $sendResultStr)			.PHP_EOL;
		$res .= sprintf("External ID: [%d]", $this->externalId)			.PHP_EOL;
		$res .= '---------------------------------------------------'	.PHP_EOL;

		return $res;
	}
}

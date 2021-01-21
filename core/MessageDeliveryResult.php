<?php

namespace core;

abstract class SendResult{
	const Success	= 0;
	const Fail		= 1;

	public static function toString(int $value): string {
		switch($value){
		case self::Success:
			return "Success";

		case self::Fail:
			return "Failure";

		default:
			throw new \LogicException("Unknown enum value [$value].");
		}
	}
}

class MessageDeliveryResult{
	private $sendResult;
	private $externalId;

	private function __construct(int $sendResult, int $externalId = null){
		$this->sendResult = $sendResult;
		$this->externalId = $externalId;
	}

	public static function SUCCESS(int $externalId): self {
		return new self(SendResult::Success, $externalId);
	}

	public static function FAIL(): self {
		return new self(SendResult::Fail);
	}

	public function getSendResult(): int {
		return $this->sendResult;
	}

	public function getExternalId(): ?int {
		return $this->externalId;
	}


	public function __toString(): string {
		$sendResultStr = SendResult::toString($this->sendResult);
		$externalIdStr = $this->externalId ?? '-';

		$res  = '--------------[MessageDeliveryResult]--------------'	.PHP_EOL;
		$res .= sprintf("Send Result: [%s]", $sendResultStr)			.PHP_EOL;
		$res .= sprintf("External ID: [%d]", $externalIdStr)			.PHP_EOL;
		$res .= '---------------------------------------------------'	.PHP_EOL;

		return $res;
	}
}

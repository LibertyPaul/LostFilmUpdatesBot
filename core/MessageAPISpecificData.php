<?php

namespace core;

interface MessageAPISpecificData{

	public function getUniqueMessageId(): int;

	public function getAPIErrorCode(): ?int;

}

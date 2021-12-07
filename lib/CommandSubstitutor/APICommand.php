<?php

namespace CommandSubstitutor;

class APICommand{
	private int $id;
	private string $API;
	private string $text;
	private int $coreCommandId;

	public function __construct(int $id, string $API, string $text, int $coreCommandId){
		$this->id = $id;
		$this->API = $API;
		$this->text = $text;
		$this->coreCommandId = $coreCommandId;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getAPI(): string {
		return $this->API;
	}

	public function getText(): string {
		return $this->text;
	}

	public function getCoreCommandId(): int {
		return $this->coreCommandId;
	}
}

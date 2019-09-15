<?php

namespace CommandSubstitutor;

class APICommand{
	private $id;
	private $API;
	private $text;
	private $coreCommandId;

	public function __construct($id, $API, $text, $coreCommandId){
		assert(is_numeric($id));
		assert(is_string($API));
		assert(is_string($text));
		assert(is_numeric($coreCommandId));

		$this->id = $id;
		$this->API = $API;
		$this->text = $text;
		$this->coreCommandId = $coreCommandId;
	}

	public function getId(){
		return $this->id;
	}

	public function getAPI(){
		return $this->API;
	}

	public function getText(){
		return $this->text;
	}

	public function getCoreCommandId(){
		return $this->coreCommandId;
	}
}

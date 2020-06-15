<?php

namespace DAL;

class ErrorDictionaryRecord{
	private $id;
	private $level;
	private $source;
	private $line;
	private $text;

	public function __construct(
		?int $id,
		string $level, # TODO: Use ENUM type?
		string $source,
		int $line,
		string $text
	){
		$this->id = $id;
		$this->level = $level;
		$this->source = $source;

		if($line < 1){
			throw new \LogicException("Line cannot be less than 1: ($line).");
		}
		else{
			$this->line = $line;
		}

		$this->text = $text;
	}

	public function getId(): ?int{
		return $this->id;
	}

	public function setId(int $id){
		if($this->id !== null){
			throw new \LogicException("Id is already set [$this->id] --x--> [$id].");
		}

		$this->id = $id;
	}

	public function getLevel(): string{
		return $this->level;
	}

	public function getSource(): string{
		return $this->source;
	}

	public function getLine(): int{
		return $this->line;
	}

	public function getText(): string{
		return $this->text;
	}

	public function __toString(){
		$idStr = $this->getId() ?? 'Null';

		$result =
			'+++++++++++++++++++[Error Record]++++++++++++++++++'	.PHP_EOL.
			sprintf('Id:                [%d]', $idStr)				.PHP_EOL.
			sprintf('Level:             [%s]', $this->getLevel())	.PHP_EOL.
			sprintf('Source:            [%s]', $this->getSource())	.PHP_EOL.
			sprintf('Line:              [%d]', $this->getLine())	.PHP_EOL.
			sprintf('Text:              [%s]', $this->getText())	.PHP_EOL.
			'+++++++++++++++++++++++++++++++++++++++++++++++++++';

		return $result;
	}
}

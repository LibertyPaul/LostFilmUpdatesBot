<?php

namespace CommandSubstitutor;

abstract class CoreCommandMap{
	const Start				= 1;
	const AddShow			= 2;
	const RemoveShow		= 3;
	const GetMyShows		= 4;
	const Mute				= 5;
	const Cancel			= 6;
	const Help				= 7;
	const Stop				= 8;
	const GetShareButton	= 9;
	const Donate			= 10;
	const Broadcast			= 11;
	const AddShowTentative	= 12;
}

class CoreCommand{
	private $id;
	private $text;

	public function __construct(int $id, string $text){
		assert($id >= CoreCommandMap::Start);
		assert($id <= CoreCommandMap::AddShowTentative);

		$this->id = $id;
		$this->text = $text;
	}

	public function getId(){
		return $this->id;
	}

	public function getText(){
		return $this->text;
	}

	public function __toString(){
		return sprintf("%s(%d)", $this->getText(), $this->getId());
	}
}

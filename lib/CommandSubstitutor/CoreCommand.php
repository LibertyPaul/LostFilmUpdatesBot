<?php

namespace CommandSubstitutor;

abstract class CoreCommandMap{
	const MIN				= 1;

	const Start				= 1;
	const AddShow			= 2;
	const RemoveShow		= 3;
	const GetMyShows		= 4;
	const Mute				= 5;
	const Cancel			= 6;
	const Help				= 7;
	const AboutTor			= 8;
	const GetShareButton	= 9;
	const Donate			= 10;
	const Stop				= 11;
	const Broadcast			= 12;
	const AddShowTentative	= 13;
	const HandleAPIError	= 14;
	const BotStats			= 15;

	const MAX				= 15;
}

class CoreCommand{
	private int $id;
	private string $text;

	public function __construct(int $id, string $text){
		if ($id < CoreCommandMap::MIN || $id > CoreCommandMap::MAX) {
			throw new \LogicException("Invalid command id: [$id].");
		}

		$this->id = $id;
		$this->text = $text;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getText(): string {
		return $this->text;
	}

	public function __toString(): string {
		return sprintf("%s(%d)", $this->getText(), $this->getId());
	}
}

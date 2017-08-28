<?php

namespace core;

abstract class UserCommandMap{
	const MIN_COMMAND	= 1;

	const Start			= 1;
	const AddShow		= 2;
	const RemoveShow	= 3;
	const GetMyShows	= 4;
	const Mute			= 5;
	const Cancel		= 6;
	const Help			= 7;
	const Stop			= 8;

	const MAX_COMMAND	= 8;
}


class UserCommand{
	private $commandId;

	public function __construct($commandId){
		if(
			$commandId < UserCommandMap::MIN_COMMAND ||
			$commandId > UserCommandMap::MAX_COMMAND
		){
			throw new \LogicException("Command Id [$commandId] is out of allowed range");
		}

		$this->commandId = $commandId;
	}

	public function getCommandId(){
		return $this->commandId;
	}

	public function __toString(){
		switch($this->commandId){
			case UserCommandMap::Start:
				$result = 'Command:Start';
				break;

			case UserCommandMap::AddShow:
				$result = 'Command:AddShow';
				break;

			case UserCommandMap::RemoveShow:
				$result = 'Command:RemoveShow';
				break;

			case UserCommandMap::GetMyShows:
				$result = 'Command:GetMyShows';
				break;

			case UserCommandMap::Mute:
				$result = 'Command:Mute';
				break;

			case UserCommandMap::Cancel:
				$result = 'Command:Cancel';
				break;

			case UserCommandMap::Help:
				$result = 'Command:Help';
				break;

			case UserCommandMap::Stop:
				$result = 'Command:Stop';
				break;

			default:
				throw new \LogicException("Command Id [$commandId] is out of allowed range");
		}

		return $result;
	}
}
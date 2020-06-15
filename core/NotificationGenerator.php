<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/DirectedOutgoingMessage.php');
require_once(__DIR__.'/APIUserDataAccessFactory.php');

require_once(__DIR__.'/../lib/LFSpecifics/LFSpecifics.php');

require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');
require_once(__DIR__.'/../lib/DAL/Shows/Show.php');
require_once(__DIR__.'/../lib/DAL/Series/Series.php');

class NotificationGenerator{
	private $config;
	private $commandSubstitutor;
	private $coreCommands;
	private $APIUserDataAccessInterfaces;
	
	public function __construct(){
		$pdo = \BotPDO::getInstance();

		$this->config = new \Config($pdo);
		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($pdo);
		$this->coreCommands = $this->commandSubstitutor->getCoreCommandsAssociative();

		$this->APIUserDataAccessInterfaces = APIUserDataAccessFactory::getInstance();
	}
	
	private function generateNewSeriesNotificationText(\DAL\Show $show, \DAL\Series $series){
		$template = 
			'<b>%s</b>: <b>S%02dE%02d</b> "%s"'	.PHP_EOL.
			'Серию можно скачать по ссылке:'	.PHP_EOL.
			'%s'
		;

		$URL = \LFSpecifics::getSeriesPageURL(
			$show->getAlias(),
			$series->getSeasonNumber(),
			$series->getSeriesNumber()
		);

		if($this->config->getValue('Notifications', 'Include Tor Advice') === 'Y'){
			$torAdviceCommnad = $this->coreCommands[\CommandSubstitutor\CoreCommandMap::AboutTor];
			$torAdvice = "Если не получается зайти на LostFilm: $torAdviceCommnad";
			$template .= PHP_EOL.PHP_EOL.$torAdvice;
		}

		return sprintf(
			$template,
			htmlspecialchars($show->getTitleRu()),
			$series->getSeasonNumber(),
			$series->getSeriesNumber(),
			htmlspecialchars($series->getTitleRu()),
			$URL
		);
	}

	public function newSeriesEvent(\DAL\Show $show, \DAL\Series $series, string $messageFormat = "%s"){
		$notificationText = $this->generateNewSeriesNotificationText($show, $series);

		return new OutgoingMessage(
			sprintf($messageFormat, $notificationText),
			null,
			new MarkupType(MarkupTypeEnum::HTML)
		);
	}

	protected function getUserFirstName(User $user){
		if(array_key_exists($user->getAPI(), $this->APIUserDataAccessInterfaces) === false){
			throw \LogicException("An UserDataAccessInterface is not defined for ".$user->getAPI());
		}

		$APIUserDataAccessInterface = $this->APIUserDataAccessInterfaces[$user->getAPI()];
		if($APIUserDataAccessInterface === null){
			throw \LogicException("An UserDataAccessInterface is null for ".$user->getAPI());
		}

		$APIUserData = $APIUserDataAccessInterface->getAPIUserDataByUserId($user->getId());
		
		return $APIUserData->getFirstName();
	}

	private function messageToAdmin(string $text){
		$admin_id = $this->config->getValue('Admin Notifications', 'Admin Id');
		if($admin_id === null){
			return null;
		}
		
		try{
			$admin = $this->usersAccess->getUserById(intval($admin_id));
			if($admin->isDeleted()){
				return null;
			}

			return new DirectedOutgoingMessage(
				$admin,
				new OutgoingMessage($text)
			);
		}
		catch(\Throwable $ex){
			return null;
		}
	}
	
	public function newUserEvent(\DAL\User $user){
		$newUserEventEnabled = $this->config->getValue('Admin Notifications', 'Send New User Event');

		if($newUserEventEnabled !== 'Y'){
			return null;
		}
		
		try{
			$userFirstName = $this->getUserFirstName($user);
			$userCount = $this->usersAccess->getActiveUsersCount(false);
		}
		catch(\Throwable $ex){
			return null;
		}

		return $this->messageToAdmin("Новый юзер $userFirstName [#$userCount]");
	}

	public function userLeftEvent(\DAL\User $user){
		$userLeftEventEnabled = $this->config->getValue('Admin Notifications', 'Send User Left Event');

		if($userLeftEventEnabled !== 'Y'){
			return null;
		}
		
		try{
			$userFirstName = $this->getUserFirstName($user);
		}
		catch(\Throwable $ex){
			return null;
		}

		return $this->messageToAdmin("Юзер $userFirstName удалился");
	}
}

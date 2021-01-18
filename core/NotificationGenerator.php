<?php

namespace core;

require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/../lib/Tracer/TracerFactory.php');
require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/OutgoingMessage.php');
require_once(__DIR__.'/../lib/CommandSubstitutor/CommandSubstitutor.php');
require_once(__DIR__.'/DirectedOutgoingMessage.php');
require_once(__DIR__.'/APIUserDataAccessFactory.php');

require_once(__DIR__.'/../lib/LFSpecifics/LFSpecifics.php');

require_once(__DIR__.'/../lib/DAL/Users/UsersAccess.php');
require_once(__DIR__.'/../lib/DAL/Users/User.php');
require_once(__DIR__.'/../lib/DAL/Shows/Show.php');
require_once(__DIR__.'/../lib/DAL/Series/Series.php');
require_once(__DIR__.'/../lib/DAL/ErrorYard/ErrorYardAccess.php');
require_once(__DIR__.'/../lib/DAL/ErrorDictionary/ErrorDictionaryAccess.php');

class NotificationGenerator{
	private $pdo;
	private $tracer;
	private $config;
	private $commandSubstitutor;
	private $coreCommands;
	private $APIUserDataAccessInterfaces;
	
	public function __construct(){
		$this->pdo = \BotPDO::getInstance();
		$this->tracer = \TracerFactory::getTracer(__CLASS__, $this->pdo);

		$this->config = \Config::getConfig($this->pdo);
		$this->commandSubstitutor = new \CommandSubstitutor\CommandSubstitutor($this->pdo);
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

	private function messageToAdmin(string $text, MarkupType $markupType = null, bool $pushEnabled = false){
		$admin_id = $this->config->getValue('Admin Notifications', 'Admin Id');
		if($admin_id === null){
			$this->tracer->logError(
				'[o]', __FILE__, __LINE__,
				'[Admin Notifications][Admin Id] value is not set.'
			);

			return null;
		}

		$admin_id = intval($admin_id);
		
		try{
			$usersAccess = new \DAL\UsersAccess($this->pdo);
			$admin = $usersAccess->getUserById($admin_id);
			if($admin->isDeleted()){
				$this->tracer->logfError(
					'[o]', __FILE__, __LINE__,
					'Admin [%d] is marked as "deleted".',
					$admin_id
				);
				
				return null;
			}

			return new DirectedOutgoingMessage(
				$admin,
				new OutgoingMessage(
					$text,
					null,
					$markupType,
					false,
					null,
					null,
					$pushEnabled
				)
			);
		}
		catch(\Throwable $ex){
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
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
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
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
			$this->tracer->logException('[o]', __FILE__, __LINE__, $ex);
			return null;
		}

		return $this->messageToAdmin("Юзер $userFirstName удалился");
	}

	public function errorYardDailyReport(){
		$errorYardAccess = new \DAL\ErrorYardAccess($this->pdo);
		$errorDictionaryAccess = new \DAL\ErrorDictionaryAccess($this->pdo);
		$activeBuckets = $errorYardAccess->getActiveErrorYardBuckets();

		$totalErrors = 0;
		$distinctErrors = count($activeBuckets);

		$rows = array();
		foreach($activeBuckets as $activeBucket){
			$totalErrors += $activeBucket->getCount();

			$errorInfo = $errorDictionaryAccess->getErrorDictionaryRecordById($activeBucket->getErrorId());
			$rows[] = sprintf(
				'<b>%s: %d</b> [%s - %s] %s:%d <pre>%s</pre>',
				$errorInfo->getLevel(),
				$activeBucket->getCount(),
				$activeBucket->getFirstAppearanceTime()->format('H:i:s'),
				$activeBucket->getLastAppearanceTime()->format('H:i:s'),
				$errorInfo->getSource(),
				$errorInfo->getLine(),
				htmlspecialchars(substr($errorInfo->getText(), 0, 128))
			);
		}

		$hasErrors = empty($rows) === false;
		$headerText = "<b>Error Yard Daily Report.</b>".PHP_EOL;
		$headerText .= "Total: $totalErrors / Distinct: $distinctErrors.".PHP_EOL.PHP_EOL;
		$headerText .= "#ErrorYard";

		$headerMessage = $this->messageToAdmin($headerText, MarkupType::HTML(), $hasErrors);

		foreach($rows as $row){
			$rowMessage = $this->messageToAdmin($row, MarkupType::HTML());
			$headerMessage->appendMessage($rowMessage);
		}

		return $headerMessage;
	}
}

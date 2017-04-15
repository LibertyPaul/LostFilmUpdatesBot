<?php
require_once(__DIR__.'/BotPDO.php');
require_once(__DIR__.'/NotificationGenerator.php');
require_once(__DIR__.'/TelegramAPI.php');
require_once(__DIR__.'/Tracer.php');

class NotificationDispatcher{
	private $notificationGenerator;
	private $telegramAPI;
	private $pdo;
	private $getNotificationDataQuery;
	private $setNotificationCodeQuery;
	private $tracer;
	
	public function __construct(NotificationGenerator $notificationGenerator, TelegramAPI $telegramAPI){
		assert($notificationGenerator !== null);
		$this->notificationGenerator = $notificationGenerator;

		assert($telegramAPI !== null);
		$this->telegramAPI = $telegramAPI;
		
		$this->tracer = new Tracer(__CLASS__);
		
		$this->pdo = BotPDO::getInstance();
		$this->getNotificationDataQuery = $this->pdo->prepare("
			SELECT 	`notificationsQueue`.`id`,
					`notificationsQueue`.`responseCode`,
					`notificationsQueue`.`retryCount`,
					`notificationsQueue`.`lastDeliveryAttemptTime`,
					`users`.`telegram_id`,
					`shows`.`title_ru` AS showTitle,
					`shows`.`alias` AS showAlias,
					`series`.`title_ru` AS seriesTitle,
					`series`.`seasonNumber`,
					`series`.`seriesNumber`
			FROM `notificationsQueue`
			JOIN `users` 	ON `notificationsQueue`.`user_id` 	= `users`.`id`
			JOIN `series` 	ON `notificationsQueue`.`series_id`	= `series`.`id`
			JOIN `shows` 	ON `series`.`show_id` 				= `shows`.`id`
			WHERE (
				`notificationsQueue`.`responseCode` IS NULL OR
				`notificationsQueue`.`responseCode` BETWEEN 400 AND 599
			) AND
				`notificationsQueue`.`retryCount` < :maxRetryCount
		");
		
		$this->setNotificationDeliveryResult = $this->pdo->prepare('
			CALL notificationDeliveryResult(:notificationId, :HTTPCode);
		');
		
	}

	private static function wasDelivered($code){
		return $code < 400;
	}

	private static function shallBeSent($responseCode, $retryCount, $lastDeliveryAttemptTime){
		if($responseCode === null){
			return true;
		}
		
		if($lastDeliveryAttemptTime === null){
			throw new Exception('lastDeliveryAttemptTime is null but responseCode is not');
		}

		if(self::wasDelivered($responseCode)){
			return false;
		}

		if($retryCount < MAX_NOTIFICATION_RETRY_COUNT){
			return false;
		}

		$waitTime = null;
		switch($retryCount){
			case 0:
				$waitTime = new DateInterval('PT0S');
				break;
			
			case 1:
				$waitTime = new DateInterval('PT1M');
				break;

			case 2:
				$waitTime = new DateInterval('PT15M');
				break;

			case 3:
				$waitTime = new DateInterval('PT1H');
				break;

			case 4:
				$waitTime = new DateInterval('P1D');
				break;

			default:
				throw Exception("Incorrect retryCount ($retryCount)");
		}
		
		$lastAttemptTime 	= new DateTime($lastDeliveryAttemptTime);
		$currentTime		= new DateTime();
		
		return $lastAttemptTime->add($waitTime) < $currentTime;
	}
	
	private static function makeURL($showAlias, $seasonNumber, $seriesNumber){
		$template = 'https://www.lostfilm.tv/series/#ALIAS/season_#SEASON_NUMBER/episode_#SERIES_NUMBER';
		return str_replace(
			array('#ALIAS', '#SEASON_NUMBER', '#SERIES_NUMBER'),
			array($showAlias, $seasonNumber, $seriesNumber),
			$template
		);
	}

	public function run(){
		$this->pdo->query('
			LOCK TABLES 
				notificationsQueue	WRITE,
				users				READ,
				series				READ,
				shows				READ;
		');

		$this->getNotificationDataQuery->execute(
			array(
				'maxRetryCount' => MAX_NOTIFICATION_RETRY_COUNT
			)
		);

		
		while($notification = $this->getNotificationDataQuery->fetch(PDO::FETCH_ASSOC)){
			$gonnaBeSent = false;
			try{
				$gonnaBeSent = self::shallBeSent(
						$notification['responseCode'],
						$notification['retryCount'],
						$notification['lastDeliveryAttemptTime']
				);
			}
			catch(Exception $ex){
				$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
				$this->tracer->logEvent('[INFO]', __FILE__, __LINE__, PHP_EOL.print_r($notification, true));
				continue;
			}
			
			if($gonnaBeSent){
				try{
					$url = self::makeURL($notification['showAlias'], intval($notification['seasonNumber']), intval($notification['seriesNumber']));	
					$message = $this->notificationGenerator->newSeriesEvent(
						intval($notification['telegram_id']), 
						$notification['showTitle'], 
						intval($notification['seasonNumber']),
						intval($notification['seriesNumber']), 
						$notification['seriesTitle'],
						$url
					);

					$deliveryResult = $this->telegramAPI->sendMessage($message);
				
					$this->setNotificationDeliveryResult->execute(
						array(
							'notificationId'	=> $notification['id'],
							'HTTPCode' 			=> $deliveryResult['code']
						)
					);
				}
				catch(PDOException $ex){
					$this->tracer->logException('[DB ERROR]', __FILE__, __LINE__, $ex);
					continue;
				}
				catch(Exception $ex){
					$this->tracer->logException('[ERROR]', __FILE__, __LINE__, $ex);
					continue;
				}
			}
		}

		$this->pdo->query('UNLOCK TABLES');
	}
}
			
				

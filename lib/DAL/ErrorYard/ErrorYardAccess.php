<?php

namespace DAL;

require_once(__DIR__.'/../CommonAccess.php');
require_once(__DIR__.'/ErrorBucketBuilder.php');

class ErrorYardAccess extends CommonAccess{
    private $getActiveErrorBucketWithLockQuery;
	private $addErrorBucketQuery;
	private $incrementErrorBucketQuery;
    private $getActiveErrorYardBucketsQuery;

    public function __construct(\PDO $pdo){
		parent::__construct(
			$pdo,
			new ErrorBucketBuilder()
		);

		$selectFields = "
			SELECT
				`ErrorYard`.`id`,
				DATE_FORMAT(`ErrorYard`.`firstAppearanceTime`, '".parent::dateTimeDBFormat."') AS firstAppearanceTimeStr,
				DATE_FORMAT(`ErrorYard`.`lastAppearanceTime`, '".parent::dateTimeDBFormat."') AS lastAppearanceTimeStr,
				`ErrorYard`.`count`,
				`ErrorYard`.`errorId`
		";

		$firstAppearanceDateIsRecent = "
			`ErrorYard`.`firstAppearanceTime` BETWEEN DATE_SUB(
				NOW(),
				INTERVAL IFNULL(
					(
						SELECT `value`
						FROM `config`
						WHERE `section` = 'ErrorYard'
						AND `item` = 'Bucket Life Period Hours'
					),
					24
				) HOUR
			) AND NOW()
		";

		$this->getActiveErrorYardBucketsQuery = $this->pdo->prepare("
			$selectFields
			FROM    `ErrorYard`
			WHERE	$firstAppearanceDateIsRecent
			ORDER BY `ErrorYard`.`firstAppearanceTime` DESC
		");

		$this->getActiveErrorBucketWithLockQuery = $this->pdo->prepare("
			$selectFields
			FROM    `ErrorYard`
			WHERE	`ErrorYard`.`errorId` = :errorId
			AND		$firstAppearanceDateIsRecent
			ORDER BY `ErrorYard`.`firstAppearanceTime` DESC
			LIMIT 1
			FOR UPDATE
		");

		$this->addErrorBucketQuery = $this->pdo->prepare("
			INSERT INTO `ErrorYard` (
				`firstAppearanceTime`,
				`lastAppearanceTime`,
				`count`,
				`errorId`
			)
			VALUES (
				NOW(),
				NOW(),
				1,
				:errorId
			)
		");

		$this->incrementErrorBucketQuery = $this->pdo->prepare("
			UPDATE `ErrorYard`
			SET	`ErrorYard`.`lastAppearanceTime` = NOW(),
				`ErrorYard`.`count` = `ErrorYard`.`count` + 1
			WHERE `ErrorYard`.`id` = :id
		");
	}

	public function getActiveErrorYardBuckets(): array {
		return $this->execute(
			$this->getActiveErrorYardBucketsQuery,
			array(),
			\QueryTraits\Type::Read(),
			\QueryTraits\Approach::Many()
		);
	}

	public function logEvent(int $errorId){
		$args = array(
			':errorId' => $errorId
		);

		$ownTransaction = $this->startTransaction();

		try{
			$res = $this->execute(
				$this->getActiveErrorBucketWithLockQuery,
				$args,
				\QueryTraits\Type::Read(),
				\QueryTraits\Approach::OneIfExists()
			);

			if($res !== null){
				$args = array(
					':id' => $res->getId()
				);

				$this->execute(
					$this->incrementErrorBucketQuery,
					$args,
					\QueryTraits\Type::Write(),
					\QueryTraits\Approach::One()
				);
			}
			else{
				$this->execute(
					$this->addErrorBucketQuery,
					$args,
					\QueryTraits\Type::Write(),
					\QueryTraits\Approach::One()
				);
			}
		}
		catch(\Throwable $ex){
			# Rollback is not needed as there is only one INSERT/UPDATE staement
			throw $ex;
		}

		if($ownTransaction){
			$this->commit();
		}
	}
}

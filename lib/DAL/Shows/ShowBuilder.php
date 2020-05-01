<?php

namespace DAL;

require_once(__DIR__.'/../DAOBuilderInterface.php');
require_once(__DIR__.'/MatchedShow.php');
require_once(__DIR__.'/Show.php');

class ShowBuilder implements DAOBuilderInterface{

	public function buildObjectFromRow(array $row, string $dateTimeFormat){
		if(array_key_exists('score', $row)){
			$show = new MatchedShow(
				intval($row['id']),
				$row['alias'],
				$row['title_ru'],
				$row['title_en'],
				$row['onAir'] === 'Y',
				\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['firstAppearanceTimeStr']),
				\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['lastAppearanceTimeStr']),
				doubleval($row['score'])
			);
		}
		else{
			$show = new Show(
				intval($row['id']),
				$row['alias'],
				$row['title_ru'],
				$row['title_en'],
				$row['onAir'] === 'Y',
				\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['firstAppearanceTimeStr']),
				\DateTimeImmutable::createFromFormat($dateTimeFormat, $row['lastAppearanceTimeStr'])
			);
		}

		return $show;
	}

}

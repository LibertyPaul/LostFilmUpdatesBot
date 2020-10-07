<?php

class LFSpecifics{
	
	public static function getSeriesPageURL(string $showAlias, int $seasonNumber, int $seriesNumber){
		return sprintf(
			'https://lostfilm.run/series/%s/season_%d/episode_%d',
			$showAlias,
			$seasonNumber,
			$seriesNumber
		);
	}

}

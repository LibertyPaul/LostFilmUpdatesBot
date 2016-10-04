<?php

require_once(__DIR__."/config/stuff.php");

function test($iterations, $callback, $args){
	$startTime = microtime(true);

	for($i = 0; $i < $iterations; ++$i){
		$callback($args);
	}
	
	$endTime = microtime(true);
	
	return $endTime - $startTime;
}

$pdoTest1 = function($args){
	$pdo = $args[0];
	$q = $pdo->prepare("
		SELECT CONCAT(
		`title_ru`,
		' (',
		`title_en`,
		')'
		) AS `title`
		FROM `tracks`
		JOIN `shows` ON `tracks`.`show_id` = `shows`.`id`
		WHERE `tracks`.`user_id` = 42
		ORDER BY `title_ru`
	");
};

$pdoTest2 = function($args){
	$pdo = $args[0];
	$q = $pdo->prepare("
		SELECT *
		FROM `tracks`
	");
};

/*
$iterations = 1000000;
$time1 = test($iterations, $pdoTest1, array(createPDO()));
$time2 = test($iterations, $pdoTest2, array(createPDO()));

echo "#1: $time1 sec per $iterations iterations".PHP_EOL;
echo "#1: $time2 sec per $iterations iterations".PHP_EOL;
*/

$query = createPDO()->prepare("
	asdasdasdasdas
");


$query->execute();

















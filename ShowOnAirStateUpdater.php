<?php
require_once(realpath(dirname(__FILE__)).'/ShowAboutParser.php');
require_once(realpath(dirname(__FILE__))."/config/stuff.php");
require_once(realpath(dirname(__FILE__))."/config/cron_actions.php");

$sap = new ShowAboutParser('CP1251');

$pdo = createPDO();

$getShow = $pdo->query("
	SELECT `id`, `url_id`, `title_ru`
	FROM `shows`
");

$updateShow = $pdo->prepare("
	UPDATE `shows`
	SET `onAir` = :onAir
	WHERE `id` = :id
");

while($show = $getShow->fetchObject()){
	$sap->loadSrc("https://www.lostfilm.tv/browse.php?cat={$show->url_id}");
	$res = $sap->run();
	$updateShow->execute(
		array(
			':onAir' => $res,
			':id' => $show->id
		)
	);
	
	echo "{$show->id} : $res\n";
}

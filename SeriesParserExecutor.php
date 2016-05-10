<?php
require_once(realpath(dirname(__FILE__))."/SeriesParser.php");

const rssURL = "http://www.lostfilm.tv/rssdd.xml";
$seriesParser = new SeriesParser();
$seriesParser->loadSrc(rssURL);
$seriesParser->run();



<?php
require_once(__DIR__."/SeriesParser.php");

const rssURL = "http://www.lostfilm.tv/rssdd.xml";
$seriesParser = new SeriesParser();
$seriesParser->loadSrc(rssURL);
$seriesParser->run();



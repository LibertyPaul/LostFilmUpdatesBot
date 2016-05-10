<?php
require_once(realpath(dirname(__FILE__))."/ShowParser.php");

const showListURL = "https://www.lostfilm.tv/serials.php";
$showParser = new ShowParser('CP1251');
$showParser->loadSrc(showListURL);
$showParser->run();


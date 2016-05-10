<?php
/*
error_reporting(E_ALL);
ini_set("display_errors", 1);
*/

require_once(__DIR__."/bot_specific_info.php");

const MAX_MESSAGE_JSON_LENGTH = 4000;//на самом деле 4163. хуй знает почему.
const BOTAN_URL = "https://api.botan.io/track";
const MEMCACHE_STORE_TIME = 86400;//1 day

date_default_timezone_set("Europe/Moscow");

$debug = false;

<?php
require_once(__DIR__.'/bot_specific_info.php');

const MAX_MESSAGE_JSON_LENGTH = 4000;// 4163 in fact. Have no idea why.
const MEMCACHE_STORE_TIME = 86400;//1 day
const MAX_NOTIFICATION_RETRY_COUNT = 5;

date_default_timezone_set('UTC');

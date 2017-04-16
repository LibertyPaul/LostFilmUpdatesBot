<?php
require_once(__DIR__.'/bot_specific_info.php');

const MEMCACHE_STORE_TIME = 86400;//1 day
const MAX_NOTIFICATION_RETRY_COUNT = 5;

date_default_timezone_set('UTC');

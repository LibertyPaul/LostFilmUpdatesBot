<?php

namespace core;

require_once(__DIR__.'/../lib/ErrorHandler.php');
require_once(__DIR__.'/../lib/ExceptionHandler.php');

require_once(__DIR__.'/AdminReports.php');

$adminReports = new AdminReports();
$adminReports->sendReports();

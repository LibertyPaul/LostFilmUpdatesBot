<?php
require_once(realpath(dirname(__FILE__))."/config.php");
require_once(realpath(dirname(__FILE__))."/../Exceptions/StdoutTextException.php");





function logAction($action){
	$fileList = get_included_files();
	$callerScript = $fileList[0];
	
	$path = realpath(dirname(__FILE__))."/../logs/cronScriptCallLog.txt";
	$cronLog = fopen($path, 'a');
	if($cronLog === false)
		throw new StdoutTextException('fopen error');
	$res = chmod($path, 0777);
	if($res === false)
		throw new StdoutTextException("chmod error");
	$res = fwrite($cronLog, "$action: [".date('d.m.Y H:i:s')."] : $callerScript\n");
	if($res === false)
		throw new StdoutTextException('fwrite error');
	$res = fclose($cronLog);
	if($res === false)
		throw new StdoutTextException('fclose error');
}

function onStart(){
	logAction("START\t");
}

function onEnd(){
	logAction("END\t");
}
	
		
		
register_shutdown_function('onEnd');
onStart();


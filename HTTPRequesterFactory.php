<?php


require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/HTTPRequester.php');
require_once(__DIR__.'/FakeHTTPRequester.php');


class HTTPRequesterFactory{

	public static function getInstance(){
		if(defined('PERFORM_ACTUAL_MESSAGE_SEND') && PERFORM_ACTUAL_MESSAGE_SEND === false){
			if(defined('UNDELIVERED_MESSAGE_STORAGE')){
				$undelifiredMessageStorage = UNDELIVERED_MESSAGE_STORAGE;
			}
			else{
				$undelifiredMessageStorage = __DIR__.'/../logs/UndeliviredMessages.log';
			}

			return new FakeHTTPRequester($undelifiredMessageStorage);
		}
		else{
			return new HTTPRequester();
		}
	}

}


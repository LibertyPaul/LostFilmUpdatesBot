<?php
require_once(__DIR__.'/UserControllerFactoryInterface.php');
require_once(__DIR__.'/UserController.php');
require_once(__DIR__.'/NotificationGenerator.php');

class UserControllerFactory implements UserControllerFactoryInterface{
	public function createBot($telegram_id){
		return new UserController($telegram_id, new NotificationGenerator());
	}
}


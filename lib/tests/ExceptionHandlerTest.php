<?php

require_once(__DIR__.'/TestsCommon.php');

class ExceptionHandlerTest extends PHPUnit_Framework_TestCase{

	public function test(){
		$key = \TestsCommon\generateRandomString(32);
		exec('php '.__DIR__."/throw.php $key");

		$this->assertTrue(
			\TestsCommon\keyExists(
				__DIR__.'/../../logs/ExceptionHandler.log',
				$key
			)
		);
	}

}







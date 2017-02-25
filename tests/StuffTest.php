<?php

require_once(__DIR__.'/../config/stuff.php');
require_once(__DIR__.'/../BotPDO.php');
require_once(__DIR__.'/../ParserPDO.php');

class StuffTest extends PHPUnit_Framework_TestCase{

	public function testDBConnection(){
		BotPDO::getInstance();
		ParserPDO::getInstance();
	}

	public function testMemcache(){
		$this->assertThat(
			createMemcache(),
			$this->logicalNot(
				$this->equalTo(null)
			)
		);
	}

}







<?php

require_once(__DIR__.'/../lib/stuff.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../parser/ParserPDO.php');

class StuffTest extends PHPUnit_Framework_TestCase{

	public function testDBConnection(){
		BotPDO::getInstance();
		ParserPDO::getInstance();
	}

	public function testMemcache(){
		$this->assertThat(
			Stuff\createMemcache(),
			$this->logicalNot(
				$this->equalTo(null)
			)
		);
	}

}







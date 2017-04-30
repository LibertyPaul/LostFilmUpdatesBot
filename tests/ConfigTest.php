<?php

require_once(__DIR__.'/../lib/Config.php');
require_once(__DIR__.'/TestsCommon.php');
require_once(__DIR__.'/OwnerPDO.php');
require_once(__DIR__.'/../core/BotPDO.php');
require_once(__DIR__.'/../parser/ParserPDO.php');

class ConfigTest extends PHPUnit_Framework_TestCase{
	private $addParameterQuery;
	private $removeParameterQuery;

	public function __construct(){
		$pdo = OwnerPDO::getInstance();

		$this->addParameterQuery = $pdo->prepare('
			INSERT INTO `config` (`section`, `item`, `value`)
			VALUES (:section, :item, :value)
		');

		$this->removeParameterQuery = $pdo->prepare('
			DELETE FROM `config`
			WHERE	`section`	= :section
			AND		`item`		= :item
		');
	}

	private function parameterGetSet($pdo){
		$section = TestsCommon\generateRandomString(255);
		$item = TestsCommon\generateRandomString(255);
		$value = TestsCommon\generateRandomString(255);

		$this->addParameterQuery->execute(
			array(
				':section'	=> $section,
				':item'		=> $item,
				':value'	=> $value
			)
		);

		$config = new Config($pdo);

		$result = $config->getValue($section, $item);
		$this->assertEquals($value, $result);

		$result = $config->getValue($section, 'Wrong item');
		$this->assertNull($result);

		$result = $config->getValue('Wrong section', $item);
		$this->assertNull($result);

		$result = $config->getValue('Wrong section', 'Wrong item');
		$this->assertNull($result);

		$this->removeParameterQuery->execute(
			array(
				':section'  => $section,
				':item'     => $item
			)
		);		
	}

	public function testBotConfigPermissions(){
		$this->parameterGetSet(BotPDO::getInstance());
	}

	public function testParserConfigPermissions(){
		$this->parameterGetSet(ParserPDO::getInstance());
	}

}





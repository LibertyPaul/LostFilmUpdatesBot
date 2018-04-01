<?php

require_once(__DIR__.'/../TracerConfig.php');

class TracerConfigTest extends \PHPUnit_Framework_TestCase{

	public function testSingleSection(){
		$config = new TracerConfig(__DIR__.'/TracerConfigSingle.ini', 'dummy');

		$this->assertEquals(500000, $config->getStandaloneIfLargerThan());
		$this->assertEquals(TracerLevel::Debug, $config->getLoggingLevel());
		$this->assertEquals(true, $config->getLogStartedFinished());
		$this->assertEquals(false, $config->getCLIStdOutTrace());
	}

	public function testComplexSection(){
		$config = new TracerConfig(__DIR__.'/TracerConfigComplex.ini', 'Section');

		$this->assertEquals(123456, $config->getStandaloneIfLargerThan());
		$this->assertEquals(TracerLevel::Event, $config->getLoggingLevel());
		$this->assertEquals(true, $config->getLogStartedFinished());
		$this->assertEquals(false, $config->getCLIStdOutTrace());
	}


}
					












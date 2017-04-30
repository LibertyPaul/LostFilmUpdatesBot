<?php
# Yes, we need to go deeper
require_once(__DIR__.'/TestsCommon.php');

class TestsCommonTest extends PHPUnit_Framework_TestCase{

	public function testGenerateRandomString(){
		$string = TestsCommon\generateRandomString(42);
		$this->assertEquals(42, strlen($string));
	}

	public function testKeyExists(){
		$tmpFileName = tempnam('/tmp', 'LFUB_tests');
		assert($tmpFileName !== false);

		$someData = TestsCommon\generateRandomString(500);
		$res = file_put_contents($tmpFileName, $someData);
		assert($res !== false);

		$subData = substr($someData, 100, 200);
		$this->assertTrue(TestsCommon\keyExists($tmpFileName, $subData));

		$differentData = TestsCommon\generateRandomString(200);
		$this->assertFalse(TestsCommon\keyExists($tmpFileName, $differentData));

		$res = unlink($tmpFileName);
		assert($res);
	}

}

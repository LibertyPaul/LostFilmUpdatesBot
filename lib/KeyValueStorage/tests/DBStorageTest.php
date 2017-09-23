<?php

require_once(__DIR__.'/../DBStorage.php');

class DBStorageTest extends PHPUnit_Framework_TestCase{
	
	public function testAll(){
		$storage = new \DBStorage('TEST_PREFIX', 100);
		
		# Test for empty result
		$value1 = $storage->getValue('testValue1');
		$this->assertNull($value1);
		
		# Test for setting a text value
		$storage->setValue('testValue1', 'zzzzzz');
		
		# Test for getting same value
		$value1 = $storage->getValue('testValue1');
		$this->assertEquals('zzzzzz', $value1);

		# Test for setting an int value
		$value2 = $storage->getValue('testValue2');
		$this->assertNull($value2);

		$storage->setValue('testValue2', '42');
		$value2 = $storage->getValue('testValue2');
		$this->assertEquals('42', $value2);

		# Test for resetting value
		$storage->setValue('testValue1', 'yyyyyy');
		$value1 = $storage->getValue('testValue1');
		$this->assertEquals('yyyyyy', $value1);

		# Test for incrementing value
		$storage->incrementValue('testValue2');
		$value2 = $storage->getValue('testValue2');
		$this->assertEquals('43', $value2);

		# Test for incrementing empty value
		$value3 = $storage->getValue('testValue3');
		$this->assertNull($value3);
		$storage->incrementValue('testValue3');
		$value3 = $storage->getValue('testValue3');
		$this->assertEquals('1', $value3);

		# Test for deleting values
		$storage->deleteValue('testValue1');
		$value1 = $storage->getValue('testValue1');
		$this->assertNull($value1);

		$storage->deleteValue('testValue2');
		$value1 = $storage->getValue('testValue2');
		$this->assertNull($value1);

		$storage->deleteValue('testValue3');
		$value1 = $storage->getValue('testValue4');
		$this->assertNull($value1);
	}
}


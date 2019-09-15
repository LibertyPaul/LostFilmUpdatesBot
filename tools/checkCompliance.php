<?php

$expectedValues = array(
	'zend.assertions' => '1'
);

foreach($expectedValues as $parameter => $expectedValue){
	$actualValue = ini_get($parameter);
	if($actualValue !== $expectedValue){
		printf(
			"ERROR %s=[%s], expected: [%s]".PHP_EOL,
			$parameter,
			$actualValue,
			$expectedValue
		);
	}
}

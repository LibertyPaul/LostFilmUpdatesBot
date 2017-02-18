#!/bin/sh

for test in $(ls *Test.php); do
	echo "###### Testing $test... ######"
	phpunit "$test"
	echo "###### Finished $test.  ######"
done

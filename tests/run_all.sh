#!/bin/bash

readonly dir=$(dirname "$0")

for php_file in $(find "$dir/.." -name "*.php" -type f); do
	php -l "$php_file"
	if [ "$?" != 0 ]; then
		echo "Aborting"
		exit 1
	fi
done

for test in $(find "$dir" -name "*Test.php" -type f); do
	echo "###### Testing $test... ######"
	phpunit "$test"
	echo "###### Finished $test.  ######"
done

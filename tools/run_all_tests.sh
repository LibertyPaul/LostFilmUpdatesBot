#!/bin/bash

readonly dir=$(dirname "$0")

for php_file in $(find "$dir/.." -name "*.php" -type f); do
	php -l "$php_file"
	if [ "$?" != 0 ]; then
		echo "Failed $php_file"
	fi
done

declare -a failedTests=()

for test in $(find "$dir/.." -name "*Test.php" -type f); do
	echo "###### Testing $test ... ######"
	
	phpunit "$test"
	if [ "$?" -ne 0 ]; then
		failedTests+="$test"
	fi
		
	echo "###### Finished $test  ######"
	echo ""
done

echo ""

if [ ${#failedTests[@]} -eq 0 ]; then
	echo "All tests were successfully passed"
	exit 0
fi

printf "Failed tests: "

for failedTest in "${failedTests[@]}"; do
	printf "%s, " "$failedTest"
done

printf "\n"

exit 1

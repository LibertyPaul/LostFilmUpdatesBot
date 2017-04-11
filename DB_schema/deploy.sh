#!/bin/bash

if [ -z "$1" ]; then
	echo "Usage: $0 <patch_directory>"
	exit 1
fi

readonly selfPath=$(pwd)
readonly path=$(readlink -m "$1")

if [ ! -z "$path" ]; then

	if [ ! -d "$path" ]; then
		echo 'Invalid schema directory path'
		exit 1
	fi

	cd "$path"
fi

if [ -f "./.my.cnf" ]; then
	readonly myCnfPath="./.my.cnf"
elif [ -f "$selfPath/.my.cnf" ]; then
	readonly myCnfPath="$selfPath/.my.cnf"
else
	echo ".my.cnf wasn't found in patch directory ($path) or near this script ($selfPath)."
	exit 1
fi

echo "Using $myCnfPath as MySQL config"

tmpFile="$(mktemp --suffix=.sql)"

declare -a elementsOrder=(
	database
	data_before
	constraints_drop
	indexes_drop
	tables
	indexes_create
	constraints_create
	triggers
	procedures
	users
	permissions
	data_after
	database_after
)

for element in "${elementsOrder[@]}"; do
	if [[ -d "./$element" && $(find "./$element/" -type f -name "*.sql" | wc -l) > 0 ]]; then
		echo "Copying $element: "
		printf "/*    $element definition:    */\n\n" >> "$tmpFile"
		for f in $(find "./$element/" -type f -name "*.sql"); do
			echo "$f "
			cat "$f" >> "$tmpFile"
			printf "\n" >> "$tmpFile"
		done
		printf "\n\n" >> "$tmpFile"
		echo "done."
	fi
done



echo "Uploading schema on MySQL server..."
res=$(mysql --defaults-file="$myCnfPath" < "$tmpFile" 2>&1)
if [[ -z "$res" ]]; then
	rm $tmpFile
	echo "Success."
else
	echo "Mysql server has returned a message: '$res'"
	echo "Please review file $tmpFile"
	exit 1
fi


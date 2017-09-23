#!/bin/bash

if [ -z "$1" ]; then
	echo "Usage: $0 <patch_directory>"
	exit 1
elif [ ! -d "$1" ]; then
	echo "$1 is not a directory. Aborting."
	exit 1
fi

readonly selfPath="$(dirname "$0")"
readonly path="$(readlink -m "$1")"

if [ ! -z "$path" ]; then

	if [ ! -d "$path" ]; then
		echo 'Invalid schema directory path'
		exit 1
	fi

	cd "$path"
fi

if [ ! -z "$2" ]; then
	patch="$2"
else
	patch="$(mktemp --suffix=.sql)"
fi

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
	if [[ -d "./$element" && $(find "./$element/" -type f -name '*.sql' | wc -l) > 0 ]]; then
		echo "Copying $element: "
		printf "/*    $element definition:    */\n\n" >> "$patch"
		for f in $(find "./$element/" -type f -name '*.sql'); do
			echo "$f "
			cat "$f" >> "$patch"
			printf "\n" >> "$patch"
		done
		printf "\n\n" >> "$patch"
		echo "done."
	fi
done

echo "Compiled. Stored in $patch"


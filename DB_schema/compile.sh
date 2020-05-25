#!/bin/bash

readonly selfDir="$(dirname "$0")"

readonly patchStructurePath="$selfDir/patchStructure.sh"
source "$patchStructurePath"
if [ $? -ne 0 ]; then
	echo "Unable to load [$patchStructurePath]. Aborting."
	exit 1
fi

readonly coloredEchoPath="$selfDir/../tools/ColoredEcho.sh"
source "$coloredEchoPath"
if [ $? -ne 0 ]; then
	echo "Unable to load [$coloredEchoPath]. Aborting."
	exit 1
fi

if [ $# -lt 1 ]; then
	echo "Usage: $0 [patch]xx [destination]"
	exit 1
fi

if [ -d "$1" ]; then
	path="$1"
elif [ -d "$selfDir/patch$1" ]; then
	path="$selfDir/patch$1"
else
	echo "Neither $1 nor $selfDir/patch$1 is not a directory. Aborting."
	exit 1
fi


if [ ! -z "$2" ]; then
	patch="$2"
else
	patch="$(mktemp --suffix=.sql)"
fi

for element in "${patchStructure[@]}"; do
	elementPath="./$path/$element"
	if [ ! -d "$elementPath" ]; then
		continue
	fi

	if [ "$(ls "$elementPath" | wc -l)" -eq 0 ]; then
		continue
	fi

	echo "Copying $element..."
	printf "/*    $element definition:    */\n\n" >> "$patch"
	for f in $(find "$elementPath" -type f -name '*.sql' | sort); do
		echo "$f"
		cat "$f" >> "$patch"
		printf "\n" >> "$patch"
	done
	printf "\n\n" >> "$patch"
	echo_green "Done."
done

echo "COMMIT;" >> "$patch"

echo "Compiled. Stored in [$patch]."


#!/bin/bash

readonly selfDir="$(dirname "$0")"

readonly coloredEchoPath="$selfDir/ColoredEcho.sh"
source "$coloredEchoPath"
if [ "$?" != "0" ]; then
	echo "Unable to load [$coloredEchoPath]. Aborting."
	exit 1
fi

if [ -z "$1" ]; then
	echo "Usage: $0 <src file> <dst file>"
	exit 1
fi

readonly srcFile="$1"
if [ ! -r "$srcFile" ]; then
	echo_red "[$srcFile] is not readable. Aborting."
	exit 1
fi

readonly dstFile="$2"
if [ -f "$dstFile" ]; then
	echo_red "[$dstFile] already exists. Aborting."
	exit 1
fi

declare -a update_ids=()
current=''

cat "$srcFile" | while IFS= read line; do
	if [[ "$line" =~ EVENT* ]]; then		
		update_id="$(echo "$current" | grep -oP "\"update_id\": \K\d*(?=,)")"

		printf "Update Id=[$update_id]"

		if [ -z "$update_id" ]; then
			current="$(printf "\n%s" "$line")";
			continue
		fi

		if test "${update_ids["$update_id"]+isset}"; then
			printf " ### Repeat. Excluded.\n"
			current="$(printf "\n%s" "$line")";
			continue
		fi

		printf "\n"

		update_ids[$update_id]=1

		echo "$current" >> "$dstFile"

		current=''

	fi
	
	current="$(printf "%s\n%s" "$current" "$line")";

done;


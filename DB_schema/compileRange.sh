#!/bin/bash

if [ $# == 0 ]; then
	echo "Usage: $0 [-i] <Patch 1 Dir> [Patch 2 Dir] ... [Patch N Dir] [Result Path]"
	exit 1
fi

readonly selfPath="$(dirname "$0")"
readonly resultTmp="$(mktemp --suffix=.sql)"
result=''

if [ "$1" == "-i" ]; then
	shift
	"$selfPath/insertValues.sh" ${@:1}
fi

while [ $# ]; do
	if (( $# > 1 )); then
		if [ ! -d "$1" ]; then
			echo "$1 is not a directory. Aborting."
			exit 1
		fi
	elif [ ! -d "$1" ]; then
		result="$1"
		break
	fi

	tmpFile="$(mktemp --suffix=.sql)"
	"$selfPath/compile.sh" "$1" "$tmpFile"
	printf "/* $1 */\n\n" >> "$resultTmp"
	cat "$tmpFile" >> "$resultTmp"
	printf "\n\n\n" >> "$resultTmp"
	rm "$tmpFile"

	shift

done

if [ ! -z "$result" ]; then
	if [ -f "$result" ]; then
		echo "$result already exists. Aborting."
		exit 1
	else
		mv "$resultTmp" "$result"
	fi
else
	echo "Success: $resultTmp"
fi


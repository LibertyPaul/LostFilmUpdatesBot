#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly coloredEchoPath="$selfDir/../tools/ColoredEcho.sh"

source "$coloredEchoPath"
if [ "$?" != "0" ]; then
	echo "source $coloredEchoPath has failed. Aborting."
	exit 1
fi

if [ $# == 0 ]; then
	echo "Usage: $0 [-i] <Patch 1 Dir> [Patch 2 Dir] ... [Patch N Dir] [Result Path]"
	exit 1
fi

readonly resultTmp="$(mktemp --suffix=.sql)"
result=''

insertFlag='N'

if [ "$1" == "-i" ]; then
	shift
	insertFlag='Y'
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
	"$selfDir/compile.sh" "$1" "$tmpFile"
	printf "/* $1 */\n\n" >> "$resultTmp"
	cat "$tmpFile" >> "$resultTmp"
	printf "\n\n\n" >> "$resultTmp"
	rm "$tmpFile"

	shift
done

echo "Compilation has finished."

if [ "$insertFlag" == 'Y' ]; then
	echo "Insert flag was set. Executing insertValues.sh."
	"$selfDir/insertValues.sh" "$resultTmp"
	if [ "$?" == "0" ]; then
		echo_green "Success."
	else
		echo_red "insertValues.sh has failed. Aborting."
		exit 1
	fi
fi

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


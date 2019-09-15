#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly coloredEchoPath="$selfDir/../tools/ColoredEcho.sh"

source "$coloredEchoPath"
if [ "$?" != "0" ]; then
	echo "source $coloredEchoPath has failed. Aborting."
	exit 1
fi

if [ -z "$1" ]; then
	echo "Usage: $0 <Patch>"
	exit 1
fi

readonly patch="$1"

declare -A values=()

readonly defaultsPath="$selfDir/patchDefaults.ini"
if [ -f "$defaultsPath" ]; then
	for line in $(cat "$defaultsPath"); do
		key="${line%%=*}"
		value="${line#*=}"

		if [ ! -z "$key" ] && [ ! -z "$value" ]; then
			echo "Default Replacement [$key] --> [$value]"
			values[$key]=$value
		fi
	done
fi

for key in $(grep -oP '&&\K\w+' "$patch" | sort -u); do
	if [ -z "${values["$key"]}" ]; then
		read -p "$key: " -e value
		values[$key]=$value
	fi
done

echo 'Starting replacement.'

for key in "${!values[@]}"; do
	value=${values[$key]}
	printf "Replacing %s -> %s ... " $key $value
	sed -i "s/&&$key/$value/g" "$patch"
	if [ "$?" == "0" ]; then
		echo_green "Success."
	else
		echo_red "Fail."
	fi
done

echo "Finished."

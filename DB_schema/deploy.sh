#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly coloredEchoPath="$selfDir/../tools/ColoredEcho.sh"

if [ -r "$coloredEchoPath" ]; then
	source "$coloredEchoPath"
else
	echo "[WARNING] $coloredEchoPath doesn't exist. Will use regular echo then."
	alias echo_red='echo'
	alias echo_yellow='echo'
	alias echo_green='echo'
fi

if [ ! -f "$1" ]; then
	echo "Usage: $0 <Compiled Patch>"
	exit 1
fi

patch="$1"

if [ -f "./.my.cnf" ]; then
	readonly myCnfPath="./.my.cnf"
elif [ -f "$selfDir/../DBCredentials/Owner.ini" ]; then
	readonly myCnfPath="$selfDir/../DBCredentials/Owner.ini"
else
	echo_red ".my.cnf wasn't found in patch directory nor near this script ($selfDir)."
	exit 1
fi

echo "Using $myCnfPath as MySQL config."


printf "Uploading schema on MySQL server ... "
res=$(mysql --defaults-file="$myCnfPath" < "$patch" 2>&1)
if [[ -z "$res" ]]; then
	echo_green "Success."
else
	echo_red "Mysql server has returned a message:"
	echo "$res"
	exit 1
fi


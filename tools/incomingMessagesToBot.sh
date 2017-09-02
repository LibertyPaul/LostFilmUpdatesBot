#!/bin/bash

readonly URL=$1
readonly password=$2
readonly incomingMessages=$3

if [ -z $URL ] || [ ! -r $incomingMessages ]; then
	echo "Usage: $0 <URL> <Password> <Incoming Messages Trace Path>"
	exit -1
fi

current=''

cat "$incomingMessages" | while read line; do
	if [[ "$line" =~ EVENT* ]]; then
		if [ -n "$current" ]; then
			path=$(mktemp)
			echo $current > $path
			./messageToBot.sh $URL $password $path Y
			rm $path
			current=''
		fi
		continue
	fi

	current="$current$line";
done;

sem --semaphorename=$$ --wait

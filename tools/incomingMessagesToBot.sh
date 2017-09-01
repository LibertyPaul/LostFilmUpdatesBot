#!/bin/bash

readonly URL=$1
readonly password=$2
readonly incomingMessages=$3

function send(){
	messageJSON=$1

	echo "$1"

	curl									\
		-i									\
		-H "Accept: application/json"		\
		-H "Content-Type:application/json"	\
		-X POST								\
		--data "$messageJSON"				\
		"$URL?password=$password"			\

}

if [ -z $URL ] || [ ! -r $incomingMessages ]; then
	echo "bad args. aborting."
	exit -1
fi

current=''

cat "$incomingMessages" | while read line; do
	if [[ "$line" =~ EVENT* ]]; then
		if [ -n "$current" ]; then
			send "$current"
			current=''
		fi
		continue
	fi

	current="$current$line";
done;



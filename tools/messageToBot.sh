#!/bin/bash

readonly URL=$1
if [ -z "$URL" ]; then
	echo "No URL provided. Aborting."
	echo "Usage: $0 <URL> <password> [text="/help"]"
	exit 1
fi

readonly password=$2
if [ -z $3 ]; then
	echo "No file provided"
	exit 1
fi

messageFile="$3"

curl									\
	-i									\
	-H "Accept: application/json"		\
	-H "Content-Type:application/json"	\
	-X POST								\
	--data "@$messageFile"				\
	"$URL?password=$password"			\

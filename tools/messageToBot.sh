#!/bin/bash

readonly URL=$1
if [ -z "$URL" ]; then
	echo "No URL provided. Aborting."
	echo "Usage: $0 <URL> <Path To Message>"
	exit 1
fi

if [ $(pwd | grep "prod" | wc -l) -gt 0 ]; then
	echo "On Prod? Really? No way!"
	exit 1
fi

if [ -z $2 ]; then
	echo "No file provided"
	exit 1
fi

readonly messageFile="$2"

curl									\
	-i									\
	-H "Accept: application/json"		\
	-H "Content-Type:application/json"	\
	-X POST								\
	--data "@$messageFile"				\
	"$URL"								\

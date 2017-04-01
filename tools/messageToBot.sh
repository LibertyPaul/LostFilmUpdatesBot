#!/bin/bash

function createMessageJSON(){
	readonly text=$1
	cat << EOF
	{
		"update_id": 912484999,
		"message": {
			"message_id": 1402,
			"from": {
				"id": 2768837,
				"first_name": "LibertyPaul",
				"username": "LibertyPaul"
			},
			"chat": {
				"id": 2768837,
				"first_name": "LibertyPaul",
				"username": "LibertyPaul",
				"type": "private"
			},
			"date": 1446162729,
			"text": "$text"
		}
	}
EOF
}

readonly URL=$1
if [ -z "$URL" ]; then
	echo "No URL provided. Aborting."
	echo "Usage: $0 <URL> <password> [text="/help"]"
	exit 1
fi

readonly password=$2
text=$3
if [ -z "$text" ]; then
	echo "No text provided. Default: /help"
	text="/help"
fi


readonly messageJSON=$(createMessageJSON "$text")

curl									\
	-i									\
	-H "Accept: application/json"		\
	-H "Content-Type:application/json"	\
	-X POST								\
	--data "$messageJSON"				\
	"$URL?password=$password"			\

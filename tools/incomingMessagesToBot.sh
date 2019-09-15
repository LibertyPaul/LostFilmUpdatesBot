#!/bin/bash

if [ $# -lt 1 ]; then
	echo "Usage: $0 <Incoming Messages Directory>"
	exit 1
fi

readonly incomingMessagesDir="$1"

if [ ! -d "$incomingMessagesDir" ]; then
	echo "Unable to access directory $incomingMessagesDir"
	exit 1
fi

readonly selfDir="$(dirname $0)"
readonly address="$("$selfDir/getConfigValue.sh" 'TelegramAPI' 'Webhook URL')"
readonly password="$("$selfDir/getConfigValue.sh" 'TelegramAPI' 'Webhook Password')"


if [ -z $address ]; then
	echo "ERROR: [TelegramAPI|Webhook URL] is not set. Aborting."
	exit 1
fi

if [ -z $password ]; then
	echo "WARNING: Password is not set"
	read -p 'Continue? [Y/n]: ' yn
	if [ -z $yn ]; then
		yn='y'
	fi

	case $yn in
		Yy) URL="$address";;
		nN) exit 0;;
		*) 	echo 'Unknown input. Aborting'
			exit 1;;
	esac
else
	URL="$address?password=$password"
fi

find "$incomingMessagesDir" -type f | xargs -n 1 -P 32 "$selfDir/messageToBot.sh" "$URL"

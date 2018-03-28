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

<<<<<<< HEAD
find "$incomingMessagesDir" -type f | xargs -n 1 -P 32 "$selfDir/messageToBot.sh" "$URL"
=======
readonly URL="$address?password=$password"

current=''
i=0

readonly messagesTmpDir=$(mktemp -d)
readonly batchSize=8

printf "Extracting messages to [$messagesTmpDir]... "

cat "$incomingMessages" | while read line; do
	if [[ "$line" =~ EVENT* ]]; then
		if [ -n "$current" ]; then
			i=$(($i+1))
			messagePath="$messagesTmpDir/$i.txt"
			echo "$current" > "$messagePath"
			current=''
		fi
		continue
	fi

	current="$current$line";
done;

printf "Done. %d messages extracted.\n" $i

echo "Sending all the messages... "
date

find "$messagesTmpDir" -type f | xargs -n 1 -P 32 "$selfDir/messageToBot.sh" $URL

printf "Done."
date

rm -r "$messagesTmpDir"
>>>>>>> 808d8f3... Multi-threaded message flooding

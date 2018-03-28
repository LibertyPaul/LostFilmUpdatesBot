#!/bin/bash


readonly incomingMessages=$1

if [ -z "$incomingMessages" ]; then
	echo "Usage: $0 <Incoming Messages Trace Path>"
	exit 1
fi

if [ ! -r $incomingMessages ]; then
	echo "Unable to access file $incomingMessages"
	exit 1
fi

readonly selfPath=$(dirname $0)
readonly address=$("$selfPath/getConfigValue.sh" 'TelegramAPI' 'Webhook URL')
readonly password=$("$selfPath/getConfigValue.sh" 'TelegramAPI' 'Webhook Password')


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
		Yy) ;;
		nN) exit 0;;
		*) 	echo 'Unknown input. Aborting'
			exit 1;;
	esac
fi

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

find "$messagesTmpDir" -type f | xargs -n 1 -P 32 "$selfPath/messageToBot.sh" $URL

printf "Done."
date

rm -r "$messagesTmpDir"

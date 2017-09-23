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
readonly URL=$("$selfPath/getConfigValue.sh" 'TelegramAPI' 'Webhook URL')
readonly password=$("$selfPath/getConfigValue.sh" 'TelegramAPI' 'Webhook Password')

if [ -z $URL ]; then
	echo "ERROR: URL is not set. Aborting."
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

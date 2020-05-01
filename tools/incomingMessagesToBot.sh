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

declare -a statuses=()
declare -a counters=()

function getStatusID(){
	local _status="$1"

	for id in "${!statuses[@]}"; do
		if [ "$_status" == "${statuses[$id]}" ]; then
			return "$id"
		fi
	done

	id="${#statuses[@]}"

	statuses=("${statuses[@]}" "$_status")
	counters=("${counters[@]}" 0)
	return "$id"
}

function incStatus(){
	local _status="$1"

	getStatusID "$_status"
	local _statusID="$?"

	counters[$_statusID]=$((${counters[$_statusID]} + 1))
}

function printCounters(){
	for _id in "${!statuses[@]}"; do
		printf "%d. %s: %d\n" "$_id" "${statuses[$_id]}" "${counters[$_id]}"
	done
}

updateCount="$(find "$incomingMessagesDir" -type f | wc -l)"
updatesSent=0

function printProgress(){
	local _updatesSent="$1"
	local _updateCount="$2"
	printf "Total messages sent: %d / %d\n" "$_updatesSent" "$_updateCount"
}


for update in $(find "$incomingMessagesDir" -type f | sort); do
	res="$("$selfDir/messageToBot.sh" "$URL" "$update" 2>/dev/null)"

	status="$(echo "$res" | tail -n 1)"
	incStatus "$status"
	updatesSent=$(($updatesSent + 1))

	clear
	printCounters
	echo ""
	printProgress $updatesSent $updateCount
done


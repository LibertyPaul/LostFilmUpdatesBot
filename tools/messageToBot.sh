#!/bin/bash

if [ $(echo "$0" | grep -c "prod") -gt 0 ]; then
	echo "On Prod? Really? No way!"
	exit 1
fi

readonly selfDir="$(dirname "$0")"

if [ $# -lt 2 ]; then
	echo "Usage 1: $0 --http <URL> <Path To Message>"
	echo "Usage 2: $0 --php-cli <Path To Message>"
	exit 1
fi

readonly mode="$1"
if [ "$mode" == "--http" ]; then
	readonly URL="$2"
	shift
fi

readonly messageFile="$2"

if [ "$mode" == "--http" ]; then
	curl									\
		-i									\
		-H "Accept: application/json"		\
		-H "Content-Type:application/json"	\
		-X POST								\
		--data "@$messageFile"				\
		"$URL"								;
elif [ "$mode" == "--php-cli" ]; then
	php "$selfDir/../TelegramAPI/tests/debug_webhook/debug_webhook.php" "$messageFile"
else
	echo "Unknown mode: [$mode]. Aborting."
	exit 1
fi

exit $?

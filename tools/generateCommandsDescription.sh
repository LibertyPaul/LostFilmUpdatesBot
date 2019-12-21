#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly as="Owner"
readonly resultToken="Note_$(cat /dev/urandom | tr -dc 'a-z0-9' | head -c 32)"
readonly query="												\
	SELECT CONCAT(text, ' - ', description) AS $resultToken		\
	FROM APICommands											\
	WHERE API = 'TelegramAPI'									\
	AND Description IS NOT NULL									\
	ORDER BY coreCommandId										\
"

"$selfDir/DBQuery.sh" "$as" "$query" | grep -oP "^$resultToken: /\K.*\$"

#!/bin/bash

readonly selfDir="$(dirname "$0")"

function man(){
	echo "Usage: $0 [--watch]"
}

if [ $# -gt 0 ]; then
	if [ "$1" == "--watch" ]; then
		watch -n 1 "$0"
	else
		echo "Incorrect argument."
		man
		exit 1
	fi
fi


readonly as="Owner"
readonly resultToken="Note_$(cat /dev/urandom | tr -dc 'a-z0-9' | head -c 32)"

readonly query="																								\
	SELECT CONCAT('Count [telegramUserData]: ', CAST(COUNT(*) AS CHAR)) AS $resultToken FROM telegramUserData	\
	UNION																										\
	SELECT CONCAT('Active users: ', CAST(COUNT(*) AS CHAR)) AS $resultToken FROM users WHERE deleted = 'N'		\
	UNION																										\
	SELECT CONCAT('Deleted users: ', CAST(COUNT(*) AS CHAR)) AS $resultToken FROM users WHERE deleted = 'Y'		\
	UNION																										\
	SELECT CONCAT('Muted users: ', CAST(COUNT(*) AS CHAR)) AS $resultToken FROM users WHERE mute = 'Y'			\
	UNION																										\
	SELECT CONCAT('Tracks: ', CAST(COUNT(*) AS CHAR)) AS $resultToken FROM tracks								\
	UNION																										\
	SELECT CONCAT('Shows: ', CAST(COUNT(*) AS CHAR)) FROM shows													\
	UNION																										\
	SELECT CONCAT('Series: ', CAST(COUNT(*) AS CHAR)) FROM series												\
"


echo "[Bot Stats:]"
"$selfDir/DBQuery.sh" "$as" "$query" | grep -oP "^$resultToken: \K.*\$"

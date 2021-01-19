#!/bin/bash

readonly selfDir="$(dirname "$0")"

if [ $# -gt 0 ]; then
	if [[ "$1" =~ ^[0-9][0-9]*$ ]]; then
		readonly limit="LIMIT $1"
	else
		echo "Incorrect limit [$1]. Aborting."
		exit 1
	fi
else
	readonly limit=""
fi

cols="$(tput cols)"
if [ $? -ne 0 ]; then
	echo "Failed to get terminal window size. Ignoring."
fi

textLength=999

if [ -n "$cols" ]; then
	otherLength=20 # Approximately though
	textLength=$(($cols - $otherLength - 5)) # -5 more just in case

	if [ $textLength -lt 10 ]; then
		textLength=10
	fi
fi

"$selfDir/DBQuery.sh"								\
	Owner 											\
	"SELECT
		ey.count,
		ed.level,
		SUBSTR(
			REPLACE(ed.text, '\n', ' '),
			1,
			$textLength
		) AS text
	FROM ErrorYard ey
	JOIN ErrorDictionary ed ON ey.errorId = ed.id
	ORDER BY ey.count DESC
	$limit"											\
	--horizontal									;

exit $?

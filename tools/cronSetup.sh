#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ]; then
	echo "Usage:"
	echo "	$0 <bot directory> <cron section label>"
fi

botDir="$(readlink -f "$1")"
if [ -z "$botDir" ]; then
	echo "Failed to get absolute path of bot dir ($1)"
	exit 1
fi

botDir="${botDir/$HOME/~}"

readonly label="$2"

crontab_before="$(mktemp --suffix=.cron)"
crontab -l > "$crontab_before"
echo "Current crontab was saved to $crontab_before"

if [ $(cat "$crontab_before" | grep -F "$botDir" | wc -l) -gt "0" ]; then
	echo "Crontab already has job with this bot ($botDir)"
	exit 1
fi

crontab_after="$(mktemp --suffix=.cron)"
cp "$crontab_before" "$crontab_after"
if [ $? != 0 ]; then
	echo "cp '$crontab_before' '$crontab_after' has failed"
	exit 1
fi

echo ""			>> "$crontab_after"
echo "# $label"	>> "$crontab_after"
echo "*	*	*	*	*	php $botDir/SeriesParserExecutor.php"			>> "$crontab_after"
echo "0	*	*	*	*	php $botDir/ShowParserExecutor.php"				>> "$crontab_after"
echo "*	*	*	*	*	php $botDir/NotificationDispatcherExecutor.php"	>> "$crontab_after"

crontab "$crontab_after"
if [ $? != 0 ]; then
	echo "Crontab file parsing error"
	echo "Please review $crontab_after"

	echo "Reverting changes..."
	crontab "$crontab_before"
	if [ $? != 0 ]; then
		echo "[CRITICAL] crontab rollback has failed."
	fi
	exit 1
fi

rm "$crontab_after"
echo "Success"

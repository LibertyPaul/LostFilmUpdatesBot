#!/bin/bash

readonly selfDir="$(dirname "$0")"

source "$selfDir/ColoredEcho.sh"
if [ $? -ne 0 ]; then
	echo "Failed to source $selfDir/ColoredEcho.sh. Aborting."
	exit 1
fi

if [ $# -lt 1 ]; then
	echo "Usage: $0 <Incoming Messages Dir> [--yes]"
	exit 1
fi

if [[ "$selfDir" =~ '/prod/' ]]; then
	echo_red "Can't run this at Prod!"
	exit 1
fi

readonly incomingMessagesDir="$1"
if [ $# -gt 1 ] && [ "$2" == "--yes" ]; then
	readonly confirmLaunch='N'
else
	readonly confirmLaunch='Y'
fi

function cleanUpDB(){
	tables=(				\
		notificationsQueue	\
		telegramUserData	\
		tracks				\
		messagesHistory		\
		series				\
		shows				\
		users				\
		ErrorYard			\
		ErrorDictionary		\
	)						;

	for table in "${tables[@]}"; do
		"$selfDir/DBQuery.sh" Owner "Delete From $table;"
		if [ $? -ne 0 ]; then
			echo_red "Failed to truncate [$table]. Aborting."
			return 1
		fi
	done

	return 0
}

function cleanUpLogs(){
	rm "$selfDir/../logs/"*
	return $?
}

function loadShows(){
	php "$selfDir/../parser/ShowParserExecutor.php" > /dev/null
	return $?
}

function loadSeries(){
	php "$selfDir/../parser/SeriesParserExecutor.php" > /dev/null
	return $?
}

function sendMessages(){
	local _incomingMessagesDir="$1"

	"$selfDir/incomingMessagesToBot.sh" "$_incomingMessagesDir"

	return $?
}

function sendNotifications(){
	php "$selfDir/../core/NotificationDispatcherExecutor.php" > /dev/null
	return $?
}

function showErrorYard(){
	"$selfDir/DBQuery.sh"								\
		Owner 											\
		"SELECT ey.count, ed.level, ed.text				\
		FROM ErrorYard ey								\
		JOIN ErrorDictionary ed ON ey.errorId = ed.id	\
		ORDER BY ey.count DESC							\
		LIMIT 10"										\
		--horizontal									;

	return $?
}

echo -n "Cleaning up the DB ... "
cleanUpDB
if [ $? -eq 0 ]; then
	echo_green "Done."
else
	echo_red "Failed. Aborting."
	exit 1
fi

echo -n "Cleaning up logs ... "
cleanUpLogs
if [ $? -eq 0 ]; then
	echo_green "Done."
else
	echo_red "Failed. Aborting."
	exit 1
fi

echo -n "Loading shows ... "
loadShows
if [ $? -eq 0 ]; then
	echo_green "Done."
else
	echo_red "Failed. Aborting."
	exit 1
fi

if [ "$confirmLaunch" == "Y" ]; then
	echo -n "About to start sending messages. Press enter to continue ... "
	read x
fi

sendMessages "$incomingMessagesDir"

echo -n "Loading latest series ... "
loadSeries
if [ $? -eq 0 ]; then
	echo_green "Done."
else
	echo_red "Failed. Aborting."
	exit 1
fi


echo -n "Sending latest notifications ... "
sendNotifications
if [ $? -eq 0 ]; then
	echo_green "Done."
else
	echo_red "Failed. Aborting."
	exit 1
fi

echo "ErrorYard top 10 entries:"
showErrorYard
if [ $? -eq 0 ]; then
	echo_green "Done."
else
	echo_red "Failed. Aborting."
	exit 1
fi




#!/bin/bash

readonly selfDir="$(dirname "$0")"

source "$selfDir/ColoredEcho.sh"
if [ $? -ne 0 ]; then
	echo "Failed to source $selfDir/ColoredEcho.sh. Aborting."
	exit 1
fi

if [ $# -lt 1 ]; then
	echo "Usage: $0 <Incoming Messages Dir> [--yes] [--php-cli]"
	exit 1
fi

if [[ "$selfDir" =~ '/prod/' ]]; then
	echo_red "Can't run this at Prod!"
	exit 1
fi

readonly incomingMessagesDir="$1"
confirmLaunch='Y'
phpCLI='N'

while [ $# -gt 1 ]; do
	case "$2" in
		"--yes")
			confirmLaunch='N'
			;;

		"--php-cli")
			phpCLI='Y'
			;;

		*)
			echo "Unknown flag [$2]. Aborting."
			exit 1
			;;
	esac
	
	shift
done

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

function createDummyServiceChat(){
	local _query="					\
		INSERT INTO users (API)		\
		VALUES ('TelegramAPI');		\
									\
		SELECT LAST_INSERT_ID();	"


	local _userId
	_userId="$("$selfDir/DBQuery.sh" Owner "$_query" \
		| grep -oP "LAST_INSERT_ID\(\): \K\d+")"

	if [ -z "$_userId" ]; then
		echo_red "Failed to create dummy user for the service chat."
		return 1
	fi

	_query="							\
		INSERT INTO telegramUserData (	\
			user_id,					\
			chat_id,					\
			type,						\
			username,					\
			first_name,					\
			last_name					\
		)								\
		VALUES (						\
			$_userId,					\
			-123456,					\
			'group',					\
			'DummyGroup',				\
			'Dummy',					\
			'Group'						\
		)"
	
	"$selfDir/DBQuery.sh" Owner "$_query"
	if [ $? -ne 0 ]; then
		echo_red "Failed to insert dummy service chat into telegramUserData."
		return 1
	fi

	_query="									\
		UPDATE config							\
		SET value = '$_userId'					\
		WHERE section = 'Admin Notifications'	\
		AND item = 'Status Channel Id'			"

	"$selfDir/DBQuery.sh" Owner "$_query"
	if [ $? -ne 0 ]; then
		echo_red "Failed to save dummy channel user_id into config."
		return 1
	fi
}

function cleanUpLogs(){
	find "$selfDir/../logs/" -type f -exec rm {} \;
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
	local _phpCLI="$2"

	if [ "$_phpCLI" == "Y" ]; then
		"$selfDir/incomingMessagesToBot.sh" "$_incomingMessagesDir" --php-cli
	else
		"$selfDir/incomingMessagesToBot.sh" "$_incomingMessagesDir"
	fi

	return $?
}

function sendNotifications(){
	php "$selfDir/../core/NotificationDispatcherExecutor.php" > /dev/null
	return $?
}

function showErrorYard(){
	"$selfDir/errorYard.sh" 10
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

echo -n "Creating dummy Service Chat ... "
createDummyServiceChat
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

sendMessages "$incomingMessagesDir" "$phpCLI"

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




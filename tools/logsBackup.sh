#!/bin/bash

readonly selfPath=$(dirname "$0")
readonly logsDir="$selfPath/../logs"
readonly logsBackupDir="$logsDir/../logsBackup"


if [ ! -d "$logsDir" ]; then
	echo "$logsDir doesn't exist"
	exit 1
fi

readonly nameFormat="%y.%m.%d_%T.tar.gz"
archiveName=$(date "+$nameFormat")

while [ -f "$logsBackupDir/$archiveName" ]; do
	echo "WARNING '$archiveName' already exists"
	
	rand=$RANDOM
	let "rand %= 5"
	sleep $rand
	archiveName=$(date "+$nameFormat")
done

if [ ! -d "$logsBackupDir" ]; then
	if [ -f "$logsBackupDir" ]; then
		echo "$logsBackupDir exists and is not a directory. Aboring."
		exit 1
	fi

	mkdir "$logsBackupDir"
	if [ "$?" != 0 ]; then
		echo "'mkdir \"$logsBackupDir\"' error"
		exit 1
	fi
fi

tar -c -vf "$logsBackupDir/$archiveName" --remove-files "$logsDir"
if [ "$?" != 0 ]; then
	exit 1
fi

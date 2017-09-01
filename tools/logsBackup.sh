#!/bin/bash

readonly selfPath=$(dirname "$0")
readonly logsDir="$selfPath/../logs"
readonly logsBackupDir="$logsDir/../logsBackup"


if [ ! -d "$logsDir" ]; then
	echo "$logsDir doesn't exist"
	exit 1
fi

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

tmpDir=$(mktemp -d --suffix='_LFUB_LOGS' 2>&1)
mv $logsDir/* $tmpDir

readonly nameFormat="%y.%m.%d_%T.tar.gz"
archiveName=$(date "+$nameFormat")

export GZIP=-9
tar cvzf "$logsBackupDir/$archiveName" --remove-files "$tmpDir"
if [ "$?" != 0 ]; then
	echo "Tar has failed. Old logs are preserved in $tmpDir"
	exit 1
fi

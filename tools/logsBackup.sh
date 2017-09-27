#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly logsDir="$selfDir/../logs"
readonly logsBackupDir="$logsDir/../logsBackup"

readonly coloredEchoPath="$selfDir/ColoredEcho.sh"
source "$coloredEchoPath"
if [ "$?" != "0" ]; then
	echo "Unable to load [$coloredEchoPath]. Aborting."
	exit 1
fi

searchMask="$1"
if [ -z "$searchMask" ]; then
	echo "SearchMask is not specified, using wildcard ... "
	searchMask="*"
fi


if [ ! -d "$logsDir" ]; then
	echo_red "$logsDir doesn't exist. Aborting."
	exit 1
fi

if [ ! -d "$logsBackupDir" ]; then
	if [ -f "$logsBackupDir" ]; then
		echo_red "$logsBackupDir exists and is not a directory. Aboring."
		exit 1
	fi

	mkdir "$logsBackupDir"
	if [ "$?" != 0 ]; then
		echo_red "'mkdir \"$logsBackupDir\"' error. Aborting."
		exit 1
	fi
fi

printf "Creating temporary directory ... "
readonly tmpDir="$(mktemp -d --suffix='_LFUB_LOGS' 2>&1)"
if [ "$?" == "0" ]; then
	printf "$tmpDir "
	echo_green "Success."
else
	echo_red "Fail. Aborting."
	exit 1
fi

for tracePath in $(find "$logsDir" -name "$searchMask" -type f); do
	traceName="$(basename "$tracePath")"
	printf "Moving $tracePath to $tmpDir/$traceName ... "
	mv "$tracePath" "$tmpDir/$traceName"
	if [ "$?" == "0" ]; then
		echo_green "Success."
	else
		echo_red "Fail."
	fi
done

if [ $(ls "$tmpDir" | wc -l) == 0 ]; then
	echo_yellow "No files matching pattern [$searchMask] were found."
	
	printf "Cleaning up ... "
	rmdir "$tmpDir"
	if [ "$?" == "0" ]; then
		echo_green "Success."
	else
		echo_red "Fail."
		exit 1
	fi

	exit 0
fi

printf "Archive name is ... "
archiveName="$(date "+%y.%m.%d_%T.tar.gz")"
if [ "$searchMask" != "*" ]; then
	archiveName="[$searchMask].$archiveName"
fi
echo "$archiveName"

echo "Compressing ... "
export GZIP=-9
tar cvzf "$logsBackupDir/$archiveName" --remove-files "$tmpDir"
if [ "$?" == 0 ]; then
	echo_green "Success."
else
	echo_red "Tar has failed. Old logs are preserved in $tmpDir."
	exit 1
fi



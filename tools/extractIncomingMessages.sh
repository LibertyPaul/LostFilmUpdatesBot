#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly logsBackupDir="$selfDir/../logsBackup"

readonly coloredEchoPath="$selfDir/ColoredEcho.sh"
source "$coloredEchoPath"
if [ "$?" != "0" ]; then
	echo "Unable to load [$coloredEchoPath]. Aborting."
	exit 1
fi

if [ -z "$1" ]; then
	echo_yellow "Destination filename was not provided."
	dstFile="$(mktemp --suffix='.incoming.log' 2>&1)"
	echo_yellow "Saving into $dstFile"
else
	dstFile="$1"
	if [ -f "$dstFile"]; then
		echo_red "[$dstFile] already exists. Aborting."
		exit 1
	fi

	touch "$dstFile"
	if [ "$?" != "0" ]; then
		echo_red "Unable to create [$dstFile]. Aborting."
		exit 1
	fi
fi

readonly tmpDir="$(mktemp -d --suffix='_LFUB_LOGS' 2>&1)"
echo "Temp dir: [$tmpDir]"

for archive in $(find "$logsBackupDir" -name '*.tar.gz' | sort); do
	echo "Extracting $archive ..."
	tar xvf "$archive" --directory "$tmpDir" --force-local
	if [ "$?" != "0" ]; then	
		echo_red "Error while extracting [$archive]. Aborting."
		exit 1
	fi

	echo "Copying incomingMessages.log ..."
	find "$tmpDir" -name 'incomingMessages.log' -exec cat {} >> "$dstFile" \;
	if [ "$?" != "0" ]; then	
		echo_red "Error while searching/copying data. Aborting."
		exit 1
	fi

	echo "Cleaning up [$tmpDir] ..."
	find "$tmpDir"/* -prune -exec rm -r {} \;

	echo_green "$archive finished"
done

rmdir "$tmpDir"

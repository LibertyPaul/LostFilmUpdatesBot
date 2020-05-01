#!/bin/bash

readonly selfDir="$(dirname "$0")"

readonly coloredEchoPath="$selfDir/ColoredEcho.sh"
source "$coloredEchoPath"
if [ "$?" != "0" ]; then
	echo "Unable to load [$coloredEchoPath]. Aborting."
	exit 1
fi

readonly srcDir="$selfDir/../logsBackup"

if [ $# -eq 0 ]; then
	readonly dstDir="$(mktemp -d --tmpdir 'LFUB_Incoming_Messages_XXXXXXXXXX')"
else
	readonly dstDir="$1"
fi

if [ ! -d "$dstDir" ]; then
	echo "$dstDir is not a directory. Aborting"
	exit 1
fi

for f in $(find "$srcDir" -type f -name '*.tar.gz' | sort); do
	tmpDir="$(mktemp -d --tmpdir 'LFUB_Incoming_Messages_XXXXXXXXXX')"
	printf "Extracting archive [$(basename "$f")] into [$tmpDir]... "

	# incomingMessages - old name, <API>.IncomingData - new name
	res="$(													\
		tar													\
		--extract											\
		--file "$f"											\
		--force-local										\
		--directory "$tmpDir"								\
		--wildcards '*incomingMessages*' '*IncomingData*'	\
		2>&1												\
	)"

	# TODO: tar returns non-zero result if any of wildcards unmatched
	# Need to create a proper validation
	#if [ $? -ne 0 ]; then
	#	if [ "$(ls --almost-all | wc -l)" -gt 0 ]; then
	#		echo_green "Done."
	#	else
	#		echo_red "Failed. Aborting."
	#		echo "$res"
	#		exit 1
	#	fi
	#fi


	for incomingMessages in $(find "$tmpDir" -type f); do
		fName="$(basename "$incomingMessages")"
		printf "Copying file [$incomingMessages]... "
		cat "$incomingMessages" >> "$dstDir/$fName"
		if [ $? -ne 0 ]; then
			echo_red "Failed. Aborting."
			exit 1
		fi

		echo_green "Done."
	done

	printf "Cleaning up... "
	rm -rf "$tmpDir"
	if [ $? -ne 0 ]; then
		echo_red "Failed. Please check $tmpDir. Continuing."
	else
		echo_green "Done."
	fi
done

echo_green "Extracting finished."

echo "Exploding into separate files..."

for current in $(find "$dstDir" -type f); do
	currentBasename="$(basename "$current")"
	category="${currentBasename%.*}"
	directory="$dstDir/$category"
	mkdir "$directory"

	cat "$current" | while read line; do
		if [[ "$line" =~ EVENT* ]] && [[ -n "$current" ]]; then
			update_id="$(echo "$current" | grep -oP '"update_id": \K(\d+)(?=,)')"
			messagePath="$directory/$update_id.json"
			if [ ! -f "$messagePath" ]; then
				echo "$current" > "$messagePath"
			fi
			current=''
		else
			current="$current$line"
		fi
	done
done

echo_green "Done."
echo "Results were stored into $dstDir"

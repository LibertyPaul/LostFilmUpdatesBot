#!/bin/bash

readonly selfDir="$(dirname "$0")"

readonly patchStructurePath="$selfDir/patchStructure.sh"
source "$patchStructurePath"
if [ $? -ne 0 ]; then
	echo "Unable to load [$patchStructurePath]. Aborting."
	exit 1
fi

readonly coloredEchoPath="$selfDir/../tools/ColoredEcho.sh"
source "$coloredEchoPath"
if [ $? -ne 0 ]; then
	echo "Unable to load [$coloredEchoPath]. Aborting."
	exit 1
fi

if [ $# -gt 0 ]; then
	readonly dstDir="$1"
else
	readonly dstDir="$selfDir"
fi

function maxPatchId(){
	maxPatch=1

	for f in $(find $dstDir -type d -name 'patch*'); do
		currentPatch=${f#./patch}
		if (( $currentPatch > $maxPatch )); then
			maxPatch=$currentPatch
		fi
	done

	echo $maxPatch
}

if [ $# -gt 1 ]; then
	readonly newPatchId="$2"
else
	readonly max="$(maxPatchId)"
	readonly newPatchId="$(($max + 1))"
fi

readonly newPatchDir="$dstDir/patch$newPatchId"

echo -n "Creating new patch with id=[$newPatchId] ($newPatchDir) ... "

mkdir "$newPatchDir"
if [ $? -ne 0 ]; then
	echo_red "Failed to create [$newPatchDir] directory. Aborting."
	exit 1
fi

for element in "${patchStructure[@]}"; do
	mkdir "$newPatchDir/$element"
	if [ $? -ne 0 ]; then
		echo_red "Failed to create [$newPatchDir/$element] directory. Aborting."
		exit 1
	fi
done

echo "USE &&db_name;" > "$newPatchDir/database/DB.sql"
if [ $? -ne 0 ]; then
	echo_red "Failed to initialize [$newPatchDir/database/DB.sql]. Aborting."
	exit 1
fi

echo_green "Done."
ls -R "$newPatchDir"


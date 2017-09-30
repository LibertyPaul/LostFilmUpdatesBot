#!/bin/bash

if [ ! -z $1 ]; then
	dstDir=$1
else
	dstDir=.
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

if [ ! -z $2 ]; then
	newPatchId=$2
else
	max=$(maxPatchId)
	newPatchId=$((max + 1))
fi

newPatchDir="$dstDir/patch$newPatchId"

echo "Creating new patch with id=[$newPatchId] ($newPatchDir) ..."

mkdir "$newPatchDir"
mkdir "$newPatchDir/database"
touch "$newPatchDir/database/DB.sql"
echo "USE &&db_name;" > "$newPatchDir/database/DB.sql"

echo "Finished:"
ls -R "$newPatchDir"

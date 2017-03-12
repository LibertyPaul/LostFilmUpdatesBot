#!/bin/bash

schema_dir=$(dirname $0)

"./$schema_dir/insertValues.sh" .

declare -a patch_order=()

patch_order+=("initial_schema")

patch_id=1
while [ -d "$schema_dir/patch$patch_id" ]; do
	patch_order+=("patch$patch_id")
	let patch_id+=1
done

for patch in ${patch_order[@]}; do
	"$schema_dir/deploy.sh" "$schema_dir/$patch"

	if [ "$?" == 0 ]; then
		echo "$patch -- Success."
	else
		echo "Failed to install $schema_dir/$patch. Aborting."
		exit 1
	fi
done

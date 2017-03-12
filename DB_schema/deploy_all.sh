#!/bin/bash

schema_dir=$(dirname $0)

"./$schema_dir/insertValues.sh" .

for patch in $(find "$schema_dir" -maxdepth 1 ! -path . -type d | sort -u); do
	"./$schema_dir/deploy.sh" "$patch"

	if [ "$?" != 0 ]; then
		echo "Failed to install $schema_dir/$patch. Aborting."
		exit 1
	fi
done

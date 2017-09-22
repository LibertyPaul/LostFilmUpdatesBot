#!/bin/bash

readonly selfDir="$(dirname "$0")"

readonly coloredEchoPath="$selfDir/../tools/ColoredEcho.sh"

if [ -r "$coloredEchoPath" ]; then
	source "$coloredEchoPath"
else
	echo "[WARNING] $coloredEchoPath doesn't exist. Will use regular echo then."
	alias echo_red='echo'
	alias echo_yellow='echo'
	alias echo_green='echo'
fi


if [ -z "$1" ]; then
	echo "Usage: $0 <Patch 1 Dir> [Patch 2 Dir] ... [Patch N Dir]"
	exit 1
fi

readonly patchDirs="${@:1}"

for sql_t_sql in $(find $patchDirs -type f -name '*.sql_t.sql'); do
	printf "[INFO] Removing $sql_t_sql ... "
	rm "$sql_t_sql"
	if [ "$?" == "0" ]; then
		echo_green "Done."
	else
		echo_red "Fail."
		echo "Unable to delete $sql_t_sql"
		echo_red "Aborting."
		exit 1
	fi
done

for sql_t in $(find $patchDirs -type f -name '*.sql_t'); do
	printf "[INFO] Copying template $sql_t to $sql_t.sql ... "
	cp "$sql_t" "$sql_t.sql"
	if [ "$?" != "0" ]; then
		echo_red "Fail. Aborting."
		exit 1
	fi
	echo_green "Done."
done

for key in $(												\
	find $patchDirs -type f -name '*.sql_t.sql' -exec cat {} \;	\
	| grep -oP '&&\w+' 										\
	| grep -oP '&&\K(\w+)'									\
	| sort -u												\
); do 
	read -p "$key: " -e value
	
	if [ -z "$value" ]; then
		echo_yellow "Warning: the value for $key is empty"
	fi

	for file in $(find $patchDirs -type f -name '*.sql_t.sql'); do
		printf "Replacing $key in $file ... "
		sed -i "s/&&$key/$value/g" "$file"
		if [ "$?" != "0" ]; then
			echo_red "Fail. Aborting."
			exit 1
		fi
		echo_green "Done."
	done
done

echo_green "Values were inserted successfully."


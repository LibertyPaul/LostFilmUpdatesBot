#!/bin/bash

if [ -z "$1" ]; then
	echo "Usage: $0 <patch directory>"
	exit 1
fi

readonly schema_dir="$(dirname $0)"
readonly path="$schema_dir/$1"

for sql_t_sql in $(find "$path" -type f -name *.sql_t.sql); do
	echo "[INFO] Removing $sql_t_sql"
	rm "$sql_t_sql"
done

for sql_t in $(find "$path" -type f -name *.sql_t); do
	echo "[INFO] Copying template $sql_t to $sql_t.sql"
	cp "$sql_t" "$sql_t.sql"
done

for key in $(grep -rPo --include=*.sql_t.sql '&&\w+' "$path" | grep -Po '&&\K(\w+)' | sort -u); do
	read -p "$key: " -e value
	
	if [ -z "$value" ]; then
		echo "Warning: the value for $key is empty"
	fi

	for file in $(find "$path" -type f -name *.sql_t.sql); do
		echo "Replacing $key in $file"
		sed -i "s/&&$key/$value/g" "$file"
	done
done

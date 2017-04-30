#!/bin/bash

readonly self_dir=$(dirname $0)
readonly logs_dir="$self_dir/../logs"

declare -a levels=(
	EVENT
	NOTICE
	WARNING
	ERROR
	CRITICAL
)

for level in "${levels[@]}"; do
	if [ -n "$(grep -lrP "^$level" "$logs_dir")" ]; then
		echo "Level: $level"
		for log in $(grep -lrP "^$level" "$logs_dir"); do
			echo "$(basename "$log"):"
			grep -nP "^$level" "$log"
		done
		echo ""
		echo ""
	fi
done

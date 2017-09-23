#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ]; then
	echo "Usage: $0 <DB Credentials Path> <query>"
	exit 1
fi

readonly DBCredentials="$1"
readonly query="$2"

mysql									\
	--defaults-file="$DBCredentials" 	\
	--execute="$query"					\
	--skip-line-numbers					\
	--i-am-a-dummy						\
	--syslog							\
	--vertical							;





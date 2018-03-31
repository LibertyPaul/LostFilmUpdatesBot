#!/bin/bash

if [ $# -lt 2 ]; then
	echo "Usage: $0 <Bot|Parser|Owner> <query>"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly role="$1"
readonly DBCredentials="$selfDir/../DBCredentials/$role.ini"
readonly query="$2"

mysql									\
	--defaults-file="$DBCredentials" 	\
	--execute="$query"					\
	--skip-line-numbers					\
	--i-am-a-dummy						\
	--syslog							\
	--vertical							;





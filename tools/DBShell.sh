#!/bin/bash

if [ $# -lt 1 ]; then
	echo "Usage: $0 <Bot|Parser|Owner>"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly role="$1"
readonly DBCredentials="$selfDir/../DBCredentials/$role.ini"

mysql									\
	--defaults-file="$DBCredentials" 	\
	--syslog							;

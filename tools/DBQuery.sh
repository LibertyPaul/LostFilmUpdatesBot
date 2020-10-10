#!/bin/bash

if [ $# -lt 2 ]; then
	echo "Usage: $0 <Bot|Parser|Owner> <Query> [--horizontal]"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly role="$1"
readonly DBCredentials="$selfDir/../DBCredentials/$role.ini"
readonly query="$2"

verticalFlag="--vertical"

if [ $# -gt 2 ]; then
	if [ "$3" == "--horizontal" ]; then
		verticalFlag=""
	else
		echo "Unknown mode [$3]. Aboritng."
		exit 1
	fi
fi


mysql									\
	--defaults-file="$DBCredentials" 	\
	--execute="$query"					\
	--skip-line-numbers					\
	--syslog							\
	$verticalFlag						;





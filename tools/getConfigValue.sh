#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ]; then
	echo "Usage: $0 <section> <item>"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly section="$1"
readonly item="$2"
readonly query="SELECT \`value\` FROM \`config\` WHERE \`section\` = '$section' AND \`item\` = '$item';"
readonly ownerCredentials="$selfDir/../DBCredentials/Owner.ini"

readonly result=$("$selfDir/DBQuery.sh" "$ownerCredentials" "$query")
readonly value=$(echo "$result" | grep -oP 'value: \K([^$]*)$')

echo "$value"

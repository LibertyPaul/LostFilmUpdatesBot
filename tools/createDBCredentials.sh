#!/bin/bash

readonly selfDir="$(dirname "$0")"
readonly DBCredentialsDir="$selfDir/../DBCredentials"

source "$selfDir/ColoredEcho.sh"
if [ "$?" != "0" ]; then
	echo "Failed to load [$selfDir/ColoredEcho.sh] script. Aborting"
	exit 1
fi

function createCredential(){
	local role="$1"
	local DBName="$2"

	case "$role" in
		"Owner")	;;
		"Bot")		;;
		"Parser")	;;
		*)
			echo_red "Invalid role=[$role]. Aborting."
			return 1;;
	esac

	if [ -z "$DBName" ]; then
		echo_red "DBName is not specified. Aborting."
		return 1
	fi

	local fName="$DBCredentialsDir/$role.ini"

	if [ -f "$fName" ]; then
		echo_yellow "$fName already exists. Skipping"
		return 0
	fi

	local confirmed=0

	while [ "$confirmed" == "0" ]; do
		read -p "$role DB User: " -e DBUser
		read -p "$role DB Password: " -e DBPassword
		
		read -p "Confirm? [Y/n]: " -e yn
		if [ "$yn" == 'Y' ] || [ -z "$yn" ]; then
			confirmed=1
		elif [ "$yn" == 'N' ]; then
			confirmed=0
		else
			echo_red "Y or N should you say. Abotring."
			return 1
		fi
	done

	echo "[mysql]"					 > "$fName"
	echo "database=\"$DBName\""		>> "$fName"
	echo "user=\"$DBUser\""			>> "$fName"
	echo "password=\"$DBPassword\""	>> "$fName"
	
	if [ "$?" == "0" ]; then
		echo_green "$role credential saved in [$fName]"
		return 0
	else
		echo_red "Failed to save credential if [$fName]"
		return 1
	fi
}

if [ ! -d "$DBCredentialsDir" ]; then
	if [ ! -f "$DBCredentialsDir" ]; then
		printf "$DBCredentialsDir doesn't exist. Creating ... "
		mkdir "$DBCredentialsDir"
		if [ "$?" != "0" ]; then
			echo_green "Success."
		else
			echo_red "Failed. Aborting."
			exit 1
		fi
	else
		echo_red "$DBCredentialsDir already exists and not a directory. Aborting."
		exit 1
	fi
fi


declare -a roles=(
	Owner
	Bot
	Parser
)

read -p "DB Name: " -e DBName

for role in "${roles[@]}"; do
	createCredential "$role" "$DBName"
	if [ "$?" != "0" ]; then
		echo_red "createCredential $role has failed."
	fi
done

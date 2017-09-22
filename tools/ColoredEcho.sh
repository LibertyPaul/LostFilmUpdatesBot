#!/bin/bash

function colored_echo(){
	colorCode="$1"
	text="$2"

	tput setaf $colorCode
	echo "$text"
	tput sgr0
}

function echo_red(){
	colored_echo 1 "$1"
}

function echo_yellow(){
	colored_echo 3 "$1"
}

function echo_green(){
	colored_echo 2 "$1"
}

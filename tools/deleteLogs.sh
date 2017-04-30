#!/bin/bash

readonly self_dir="$(dirname "$0")"
readonly logs_dir="$self_dir/../logs"

rm -f $logs_dir/*

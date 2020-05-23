#!/bin/bash

unset patchStructure

declare -a patchStructure=(
	database
	data_before
	constraints_drop
	indexes_drop
	tables
	indexes_create
	constraints_create
	triggers
	procedures
	users
	permissions
	data_after
	database_after
)


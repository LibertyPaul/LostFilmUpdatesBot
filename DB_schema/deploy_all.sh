#!/bin/sh

./insertValues.sh .

for patch in $(find . -maxdepth 1 ! -path . -type d | sort -u); do
	./deploy.sh "$patch"
done

#!/bin/bash

if [ -z "$1" ]; then
	echo "Usage: $0 <Link Path> [-f]"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly webhookPath="$selfDir/../TelegramAPI/webhook.php"
readonly linkPath="$1"
readonly force="$2"


function getValueOrDie {
	readonly getConfigValueScriptPath="$selfDir/getConfigValue.sh"
	readonly section="$1"
	readonly item="$2"

	readonly value="$("$getConfigValueScriptPath" "$section" "$item")"
	if [ $? != 0 ]; then
		echo "$getConfigValueScriptPath has triggered an error. Aborting."
		exit 1
	fi

	if [ -z "$value" ]; then
		echo "Value [$section][$item] doesn't exist. Aborting."
		exit 1
	fi

	echo "$value"
}

# Fetching parameters from DB

readonly token=$(getValueOrDie 'TelegramAPI' 'token')
echo "[DB] token=[$token]"

webhookURL="$(getValueOrDie 'TelegramAPI' 'Webhook URL')"
echo "[DB] webhookURL=[$webhookURL]"

readonly webhookPassword="$(getValueOrDie 'TelegramAPI' 'Webhook Password')"
echo "[DB] webhookPassword=[$webhookPassword]"

if [ -n "$webhookPassword" ]; then
	webhookURL="$webhookURL?password=$webhookPassword"
fi

# Checking link directory under www root. Creating if doesn't exist

readonly linkDir="$(dirname "$linkPath")"
if [ ! -d "$linkDir" ]; then
	echo "[INFO] Creating directory for symlink: '$linkDir'"
	mkdir -p "$linkDir"
	if [ "$?" != "0" ]; then
		echo "mkdir -p "$linkDir" has failed ($?)"
		exit 1
	fi
fi

# Checking if script webhook exists

readonly absWebhookPath=$(readlink -f "$webhookPath")
if [ -z "$absWebhookPath" ]; then
	echo "Bot webhook doesn't exist ($webhookPath)($absWebhookPath)"
	exit 1
fi

# Checking case when symlink already exists. Continue in case of -f flag.

if [ -e "$linkPath" ]; then
	if [ "$force" == "-f" ]; then
		echo "Removing old link..."
		rm -f "$linkPath"
		if [ "$?" != "0" ]; then
			echo "Error on 'rm -f $linkPath'. Aborting."
			exit 1
		fi
	else
		echo "Link already exists. Please remove it first: $absLinkPath or use -f option"
		exit 1
	fi
fi

readonly absLinkPath=$(readlink -m "$linkPath")

# Showing values

echo "[INFO] Setting symlink '$absLinkPath' -> '$absWebhookPath'"
ln -s "$absWebhookPath" "$absLinkPath"
if [ $? != 0 ]; then
	exit 1
fi

echo "[INFO] Setting url=$webhookURL and allowed_updates=message"
echo "[INFO] Telegram API URL: 'https://api.telegram.org/bot$token/setWebhook'"
read -p "Please confirm[y/n]: " yn
if [ "$yn" != "y" ] && [ "$yn" != "Y" ]; then
	exit
fi

# Sending request

http_code=$(\
	curl											\
	--write-out %{http_code}						\
	--silent										\
	--output /tmp/setWebhook.json					\
	--data-urlencode "url=$webhookURL"				\
	--data-urlencode "allowed_updates=message"		\
	"https://api.telegram.org/bot$token/setWebhook"	\
)

# Showing result & Cleaning up

cat /tmp/setWebhook.json
rm /tmp/setWebhook.json
echo ""

if [ "$http_code" != "200" ]; then
	echo "Telegram API responded with ($http_code):"
	exit 1
fi



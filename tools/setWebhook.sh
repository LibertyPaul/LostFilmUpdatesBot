#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ]; then
	echo "Usage: $0 <API Name> [-f]"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly APIName="$1"
readonly linkPath="$2"
readonly force="$3"

readonly getConfigValueScriptPath="$selfDir/getConfigValue.sh"

readonly token="$("$getConfigValueScriptPath" "$APIName" 'token')"
if [ -z "$token" ]; then
	echo "[$APIName][token] is not found"
	exit 1
fi

echo "[DB] token=[$token]"

webhookURL="$("$getConfigValueScriptPath" 'Webhook' 'URL')"
if [ -z "$webhookURL" ]; then
	echo '[Webhook][URL] is not found'
	exit 1
fi

echo "[DB] webhookURL=[$webhookURL]"

readonly webhookPassword="$("$getConfigValueScriptPath" 'Webhook' 'Password')"
if [ -z "$webhookPassword" ]; then
	echo '[Webhook][Password] is not found'
	exit 1
fi

echo "[DB] webhookPassword=[$webhookPassword]"

readonly APIDir="$selfDir/../$APIName"

if ! [ -d "$APIDir" ]; then
	echo "API '$APIName' does not exist under '$APIDir'"
	exit 1
fi

readonly webhookPath="$APIDir/webhook.php"

readonly linkDir="$(dirname "$linkPath")"
if [ ! -d "$linkDir" ]; then
	echo "[INFO] Creating directory for symlink: '$linkDir'"
	mkdir -p "$linkDir"
	if [ "$?" != "0" ]; then
		echo "mkdir -p "$linkDir" has failed ($?)"
		exit 1
	fi
fi

absWebhookPath=$(readlink -f "$webhookPath")
if [ -z "$absWebhookPath" ]; then
	echo "Bot webhook doesn't exist ($webhookPath)($absWebhookPath)"
	exit 1
fi

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

absLinkPath=$(readlink -m "$linkPath")

echo "[INFO] Setting symlink '$absLinkPath' -> '$absWebhookPath'"
ln -s "$absWebhookPath" "$absLinkPath"
if [ $? != 0 ]; then
	exit 1
fi

if [ -n "$webhookPassword" ]; then
	webhookURL="$webhookURL?password=$webhookPassword"
fi

echo "[INFO] Setting url=$webhookURL and allowed_updates=message"
echo "[INFO] Telegram API URL: 'https://api.telegram.org/bot$token/setWebhook'"
read -p "Please confirm[y/n]: " yn
if [ "$yn" != "y" ] && [ "$yn" != "Y" ]; then
	exit
fi

http_code=$(\
	curl											\
	--write-out %{http_code}						\
	--silent										\
	--output /tmp/setWebhook.json					\
	--data-urlencode "url=$webhookURL"				\
	--data-urlencode "allowed_updates=message"		\
	"https://api.telegram.org/bot$token/setWebhook"	\
)

cat /tmp/setWebhook.json
rm /tmp/setWebhook.json
echo ""

if [ "$http_code" != "200" ]; then
	echo "Telegram API responded with ($http_code):"
	exit 1
fi



#!/bin/bash

if [ -z "$1" ]; then
	echo "Usage: $0 <link_path>"
	exit 1
fi

readonly selfDir="$(dirname "$0")"
readonly getConfigValueScriptPath="$selfDir/getConfigValue.sh"

readonly token="$("$getConfigValueScriptPath" 'TelegramAPI' 'token')"
if [ -z "$token" ]; then
	echo '[TelegramAPI][token] is not found'
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

readonly webhookPath="$selfDir/../webhook/webhook.php"
readonly linkPath="$1"

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
	echo "Bot webhook doesn't exist ($absWebhookPath)"
	exit 1
fi

if [ -e "$linkPath" ]; then
	echo "Link already exists. Please remove it first: $absLinkPath"
	exit 1
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



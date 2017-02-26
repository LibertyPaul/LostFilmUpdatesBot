#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ] || [ -z "$3" ]; then
	echo "Usage:"
	echo "	$0 <bot_token> <link_path> <URL> [webhook_password]"
	exit 1
fi

readonly token="$1"
readonly webhookPath="../webhook/webhook.php"
readonly linkPath="$2"
webhookURL="$3"
readonly webhookPassword="$4"

readonly linkDir=$(dirname "$linkPath")
if [ ! -d "$linkDir" ]; then
	echo "[INFO] Creating directory for symlink: '$linkDir'"
	mkdir -p "$linkDir"
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
read -p "please confirm[y/n]: " yn
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
echo ""

if [ "$http_code" != "200" ]; then
	echo "Telegram API responded with ($http_code):"
	exit 1
fi



<?php

interface TelegramBotFactoryInterface{
	public function createBot($telegram_id, $chat_id = null);
}



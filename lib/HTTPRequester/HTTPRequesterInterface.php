<?php

class HTTPException extends Exception{}

interface HTTPRequesterInterface{
	public function sendJSONRequest($destination, $content_json);
	public function sendGETRequest($destination);
}

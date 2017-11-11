<?php

namespace HTTPRequester;

require_once(__DIR__.'/HTTPRequestProperties.php');

class HTTPException extends \Exception{}

interface HTTPRequesterInterface{
	public function request(HTTPRequestProperties $requestProperties);
	public function multiRequest(array $requestsProperties);
}

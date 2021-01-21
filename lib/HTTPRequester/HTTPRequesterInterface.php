<?php

namespace HTTPRequester;

require_once(__DIR__.'/HTTPRequestProperties.php');

class HTTPException extends \RuntimeException{}
class HTTPTimeoutException extends HTTPException{}

interface HTTPRequesterInterface{
	public function request(HTTPRequestProperties $requestProperties): HTTPResponse;
	public function multiRequest(array $requestsProperties): array;
}

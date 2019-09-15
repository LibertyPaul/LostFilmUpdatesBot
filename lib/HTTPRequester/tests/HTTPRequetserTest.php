<?php

namespace HTTPRequester;

require_once(__DIR__.'/../HTTPRequester.php');

class ConversationStorageTest extends \PHPUnit_Framework_TestCase{
	const OK = 1;
	const FAIL = 2;
	const ERROR = 3;

	private static function createTestCases(){
		$validURL = 'https://libertypaul.ru';
		$notExistingURL = 'https://libertypaul.ru/invalid_page.html';
		$invalidURL = '------------------.---';

		$emptyPayload1 = array();
		$emptyPayload2 = '';
		$emptyPayload3 = null;

		$payload1 = array('a' => 'b', 'c' => 'd');
		$payload2 = 'a=b&c=d';
		$payloadJSON = json_encode($payload1);
		$invalidPayload = new HTTPRequester();
		
		$get	= RequestType::Get;
		$post	= RequestType::Post;

		$textHTML	= ContentType::TextHTML;
		$multipart	= ContentType::MultipartForm;
		$JSON		= ContentType::JSON;

		$cases = array(
			array($get,		$textHTML,	$validURL, $emptyPayload1,	self::OK),
			array($post,	$multipart,	$validURL, $emptyPayload1,	self::OK),
			array($get,		$JSON,		$validURL, $emptyPayload1,	self::OK),
			array($get,		$textHTML,	$validURL, $payload1,		self::OK),
			array($post,	$multipart,	$validURL, $payload1,		self::OK),
			array($get,		$JSON,		$validURL, $payloadJSON,	self::OK),
			array($post,	$textHTML,	$validURL, $payload2,		self::OK),
			array($get,		$multipart,	$validURL, $payload2,		self::OK),
			array($post,	$JSON,		$validURL, $payloadJSON,	self::OK),

			array($get,		$textHTML,	$notExistingURL, $emptyPayload1,	self::FAIL),
			array($post,	$multipart,	$notExistingURL, $emptyPayload1,	self::FAIL),
			array($get,		$JSON,		$notExistingURL, $emptyPayload1,	self::FAIL),
			array($get,		$textHTML,	$notExistingURL, $payload1,			self::FAIL),
			array($post,	$multipart,	$notExistingURL, $payload1,			self::FAIL),
			array($get,		$JSON,		$notExistingURL, $payloadJSON,		self::FAIL),
			array($post,	$textHTML,	$notExistingURL, $payload2,			self::FAIL),
			array($get,		$multipart,	$notExistingURL, $payload2,			self::FAIL),
			array($post,	$JSON,		$notExistingURL, $payloadJSON,		self::FAIL),

			array($get,		$textHTML,	$invalidURL, $emptyPayload1,	self::ERROR),
			array($post,	$multipart,	$invalidURL, $emptyPayload1,	self::ERROR),
			array($get,		$JSON,		$invalidURL, $emptyPayload1,	self::ERROR),
			array($get,		$textHTML,	$invalidURL, $payload1,			self::ERROR),
			array($post,	$multipart,	$invalidURL, $payload1,			self::ERROR),
			array($get,		$JSON,		$invalidURL, $payloadJSON,		self::ERROR),
			array($post,	$textHTML,	$invalidURL, $payload2,			self::ERROR),
			array($get,		$multipart,	$invalidURL, $payload2,			self::ERROR),
			array($post,	$JSON,		$invalidURL, $payloadJSON,		self::ERROR),

			array($get,		$textHTML,	$validURL, $invalidPayload,	self::ERROR),
			array($post,	$multipart,	$validURL, $invalidPayload,	self::ERROR),
			array($get,		$JSON,		$validURL, $invalidPayload,	self::ERROR),
			array($get,		$textHTML,	$validURL, $invalidPayload,	self::ERROR),
			array($post,	$multipart,	$validURL, $invalidPayload,	self::ERROR),
			array($get,		$JSON,		$validURL, $invalidPayload,	self::ERROR),
			array($post,	$textHTML,	$validURL, $invalidPayload,	self::ERROR),
			array($get,		$multipart,	$validURL, $invalidPayload,	self::ERROR),
			array($post,	$JSON,		$validURL, $invalidPayload,	self::ERROR),
		);

		return $cases;
	}


	public function testSingle(){
		$requester = new HTTPRequester();
		
		$cases = self::createTestCases();

		foreach($cases as $case){
			
			$raised = false;
			$result = null;
			$exception = null;

			try{
				$properties = new HTTPRequestProperties(
					$case[0],
					$case[1],
					$case[2],
					$case[3]
				);

				$result = $requester->request($properties);
			}
			catch(\Exception $ex){
				$raised = true;
				$exception = $ex;
			}

			switch($case[4]){
				case self::OK:
					if($raised){
						throw $exception;
					}
					$this->assertTrue($raised === false);
					$this->assertTrue($result !== null);
					if($result !== null){
						$this->assertEquals(200, $result->getCode());
					}

					break;

				case self::FAIL:
					$this->assertTrue($raised === false);
					$this->assertTrue($result !== null);
					if($result !== null){
						$this->assertTrue($result->getCode() >= 400);
					}

					break;

				case self::ERROR:
					$this->assertTrue($raised);

					break;

				default:
					throw new \LogicException("Invalid expected result: ($case[4])");
			}
		}
	}

	#TODO: create test for MultiRequest
}
					












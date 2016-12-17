<?php
require_once(__DIR__.'/../config/stuff.php');

class StuffTest extends PHPUnit_Framework_TestCase{

	private function checkBothDirections($str, $openingPos, $closingPos){
		$res = findMatchingParenthesis($str, $openingPos);
		$this->assertEquals($res, $closingPos);
		
		$res = findMatchingParenthesis($str, $closingPos);
		$this->assertEquals($res, $openingPos);
	}
	
	private function checkFindParenthesisThrows($str, $openingPos){
		$throws = false;
		
		try{
			findMatchingParenthesis($str, $openingPos);
		}
		catch(StdoutTextException $ex){
			$throws = true;
		}
		
		$this->assertTrue($throws);
	}
		
	public function testFindMatchingParenthesis(){
		$case1 = 'xxxxx(sss)xxxxx';
		$this->checkBothDirections($case1, 5, 9);
		
		$case2 = '#####(ooo(zzz(aaa)zzz)ooo)#####';
		$this->checkBothDirections($case2, 5, 25);
		$this->checkBothDirections($case2, 9, 21);
		$this->checkBothDirections($case2, 13, 17);
		
		$case3 = '#####[ooo{zzz<aaa>zzz}ooo]#####';
		$this->checkBothDirections($case3, 5, 25);
		$this->checkBothDirections($case3, 9, 21);
		$this->checkBothDirections($case3, 13, 17);
		
		$case4 = '(non-closing-parenthesis';
		$res = findMatchingParenthesis($case4, 0);
		$this->assertFalse($res);
		
		$case5 = '##############';
		$this->checkFindParenthesisThrows($case5, 0);
		
		$case6 = 'zzz';
		$this->checkFindParenthesisThrows($case6, 100500);
	}

	public function testDBConnection(){
		$this->assertThat(
			createPDO(),
			$this->logicalNot(
				$this->equalTo(null)
			)
		);
	}

	public function testMemcache(){
		
		$this->assertThat(
			createMemcache(),
			$this->logicalNot(
				$this->equalTo(null)
			)
		);
	
	}

	
	
	


		
}







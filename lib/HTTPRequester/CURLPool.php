<?php

namespace HTTPRequester;

class CURLPool{
	private $handles = array();

	public function __construct($size = 0){
		$this->add($size);
	}

	public function __destruct(){
		foreach($this->handles as $handle){
			curl_close($handle);
		}
	}

	private function add($count){
		for($i = 0; $i < $count; ++$i){
			$newHandle = curl_init();
			assert($newHandle);

			$this->handles[] = $newHandle;
		}
	}

	public function size(){
		return count($this->handles);
	}

	public function reserve($count){
		assert(is_int($count));
		$this->add($count - $this->size());
	}

	public function getByIndex($index){
		assert(is_int($index));
		$maxIndex = $this->size() - 1;
		if($index > $maxIndex){
			throw new \OutOfRangeException(
				"Requested index is out of range: $index / $maxIndex"
			);
		}

		return $this->handles[$index];
	}

	public function getFirst(){
		return $this->getByIndex(0);
	}
}

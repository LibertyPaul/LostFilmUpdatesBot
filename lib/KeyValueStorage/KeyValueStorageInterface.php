<?php

interface KeyValueStorageInterface{
	public function getValue($key);
	public function setValue($key, $value);
	public function incrementValue($key);
	public function deleteValue($key);
}

<?php

#TODO: Tracer namespace

require_once(__DIR__.'/FileTracer.php');
require_once(__DIR__.'/YardTracer.php');

class TracerFactory{
	public static function getTracer(
		string $traceName = null,
		\PDO $pdo = null,
		bool $fileTracerRequired = true,
		bool $yardTracerRequired = true
	): \TracerBase {
		if($yardTracerRequired && $pdo === null){
			throw new \LogicException("YardTracer requires PDO.");
		}

		if($fileTracerRequired && $traceName === null){
			throw new \LogicException("FileTracer requires traceName.");
		}

		if($yardTracerRequired && $fileTracerRequired){
			$yardTracer = new \YardTracer($pdo);
			$fileTracer = new \FileTracer($traceName, $yardTracer);
			
			return $fileTracer;
		}
		elseif($yardTracerRequired){
			$yardTracer = new \YardTracer($pdo);
			return $yardTracer;
		}
		elseif($fileTracerRequired){
			$fileTracer = new \FileTracer($traceName);
			return $fileTracer;
		}
		else{
			# Going to explode
			return null;
		}
	}
}

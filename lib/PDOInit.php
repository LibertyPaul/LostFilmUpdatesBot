<?php

class PDOInit{
	private static function getCredentials($credentialsFile){
		$values = parse_ini_file($credentialsFile);
		if($values === false){
			throw new \RuntimeException("parse_ini_file error '$credentialsFile'");
		}

		$result = array();

		if(array_key_exists('database', $values)){
			$result['database'] = $values['database'];
		}
		else{
			throw new \RuntimeException("Database is not specified in '$credentialsFile'");
		}

		if(array_key_exists('user', $values)){
			$result['user'] = $values['user'];
		}
		else{
			throw new \RuntimeException("Username is not specified in '$credentialsFile'");
		}

		if(array_key_exists('password', $values)){
			$result['password'] = $values['password'];
		}
		else{
			throw new \RuntimeException("Password is not specified in '$credentialsFile'");
		}

		return $result;
	}

	public static function initialize($credentialsFile){
		$credentials = self::getCredentials($credentialsFile);

		$instance = new PDO(
			'mysql:dbname='.$credentials['database'].';host=localhost;charset=utf8mb4',
			$credentials['user'],
			$credentials['password'],
			array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			)
		);
		
		return $instance;
	}
}

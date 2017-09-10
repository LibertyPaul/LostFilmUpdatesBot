<?php

class PDOInit{
	private static function getValue($src, $key, $default = null){
		if(array_key_exists($key, $src) == false){
			# func_num_args to ensure that $default was explicitly set in call
			if(func_num_args() > 2){
				return $default;
			}

			throw new \RuntimeException("$src was not found in ini file '$credentialsFile'");
		}

		return $src[$key];
	}

	private static function getCredentials($credentialsFile){
		$iniFileData = parse_ini_file($credentialsFile, true);
		if($iniFileData === false){
			throw new \RuntimeException("parse_ini_file error '$credentialsFile'");
		}

		$MySQLConfig = self::getValue($iniFileData, 'mysql');

		$result = array(
			'host' => self::getValue($MySQLConfig, 'host', 'localhost'),
			'database' => self::getValue($MySQLConfig, 'database'),
			'charset' => self::getValue($MySQLConfig, 'charset', 'utf8mb4'),
			'user' => self::getValue($MySQLConfig, 'user'),
			'password' => self::getValue($MySQLConfig, 'password')
		);

		return $result;
	}

	public static function initialize($credentialsFile){
		$credentials = self::getCredentials($credentialsFile);

		$instance = new \PDO(
			sprintf(
				'mysql:dbname=%s;host=%s;charset=%s',
				$credentials['database'],
				$credentials['host'],
				$credentials['charset']
			),
			$credentials['user'],
			$credentials['password'],
			array(
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				\PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
					"SET time_zone = '%s'",
					date_default_timezone_get()
				)
			)
		);
		
		return $instance;
	}
}

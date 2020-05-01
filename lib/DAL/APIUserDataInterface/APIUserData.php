<?php

namespace DAL;

interface APIUserData{
	public function getUserId();
	public function getAPISpecificId();
	public function getUsername();
	public function getFirstName();
	public function getLastName();

	public function __toString();
}

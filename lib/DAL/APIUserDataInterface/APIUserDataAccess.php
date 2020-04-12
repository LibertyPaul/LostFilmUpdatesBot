<?php

namespace DAL;

interface APIUserDataAccess{
	public function getAPIUserDataByUserId(int $user_id);
}

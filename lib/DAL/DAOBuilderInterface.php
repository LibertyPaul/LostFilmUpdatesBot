<?php

namespace DAL;

interface DAOBuilderInterface{
	public function buildObjectFromRow(array $row, string $dateTimeFormat);
}

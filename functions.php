<?php

function get_week_start_end( $week, $year ) {
	$dto = new DateTime();

	// $dayOfWeek = 0, Sunday - Saturday.
	$dto->setISODate($year, $week, 0);
	$ret['week_start'] = $dto->format('m-d-Y');
	$ret['week_start_system'] = $dto->format('Y-m-d');
	$ret['week_start_day'] = $dto->format('d');
	$ret['week_start_month'] = $dto->format('F');
	$dto->modify('+6 days');
	$ret['week_end'] = $dto->format('m-d-Y');
	$ret['week_end_system'] = $dto->format('Y-m-d');
	$ret['week_end_day'] = $dto->format('d');
	$ret['week_end_month'] = $dto->format('F');
	return $ret;
}

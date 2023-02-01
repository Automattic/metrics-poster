<?php

namespace MetricPoster;

use GuzzleHttp\Client;

class MetricFetcher
{

	public array $date_range;
	public string $clientid;

	function __construct( $week = '', $year = '', $clientid = '' )
	{
		print "In MetricFetcher constructor\n";
		$date_range = get_week_start_end((int) $week, $year);
		$this->date_range = $date_range;
		$this->clientid = $clientid;
	}

	function get_new_relic_slow_transactions(){
		return 12;
	}

	function get_kibana_php_errors(){
		return 8700;
	}

	function get_kibana_php_warnings(){
		return 123123;
	}
}

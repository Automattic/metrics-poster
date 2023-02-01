<?php

declare(strict_types=1);

namespace MetricPoster;

use GuzzleHttp\Client;

class NewRelic
{

	const NR_API_URL = 'https://api.newrelic.com/v2/applications/';
	const NR_API_QUERY = 'metrics/data.json?';
	public array $date_range;
	public string $clientid;
	public string $metrics;
	public Client $client;
	public string $metrics_query_obj;
	public $metrics_data;

	public function __construct( $week = '', $year = '', $clientid = '', $metrics = '' )
	{
		print "In NewRelic constructor\n";
		$date_range = get_week_start_end((int) $week, $year);
		$this->date_range = $date_range;
		$this->clientid = $clientid;
		$this->metrics = $metrics;
		$this->client = new Client([
			'base_uri' => self::NR_API_URL,
			'headers' => [
				'X-Api-Key' => $_ENV['NEW_RELIC_API_KEY'],
			],
		]);
		
		$this->metrics_query_obj =  $this->build_metrics_query_object();
		// echo $this->metrics_query_obj . "\n";
		$this->metrics_data = $this->fetch_metrics_data();
	}

	public function get_metrics_data(){
		return (array) $this->metrics_data ?? [];
	}

	public function fetch_metrics_data(){
		$response = $this->client->request('GET', $this->clientid . '/' . self::NR_API_QUERY . $this->metrics_query_obj);
		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function build_metrics_query_object(){
		$metrics = explode(',', $this->metrics);
		$metrics_query_obj = [];
		foreach($metrics as $metric){
			$metric = strtolower($metric);
			switch ($metric) {
				case 'apdex':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Apdex',
						'EndUser/Apdex',
					]);
					break;
				case 'slowest_transactions':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'WebTransaction/Uri/*',
						'WebTransaction/NormalizedUri/*',
					]);
					break;
				case 'php_errors':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/all',
						'Errors/allWeb',
						'Errors/allOther',
					]);
					break;
				case 'php_warnings':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Warning',
						'Errors/php/Warning/all',
						'Errors/php/Warning/allWeb',
						'Errors/php/Warning/allOther',
					]);
					break;
				case 'php_notices':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Notice',
						'Errors/php/Notice/all',
						'Errors/php/Notice/allWeb',
						'Errors/php/Notice/allOther',
					]);
					break;
				case 'php_deprecated':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Deprecated',
						'Errors/php/Deprecated/all',
						'Errors/php/Deprecated/allWeb',
						'Errors/php/Deprecated/allOther',
					]);
					break;
				case 'php_strict':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Strict',
						'Errors/php/Strict/all',
						'Errors/php/Strict/allWeb',
						'Errors/php/Strict/allOther',
					]);
					break;
				case 'php_recoverable':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Recoverable',
						'Errors/php/Recoverable/all',
						'Errors/php/Recoverable/allWeb',
						'Errors/php/Recoverable/allOther',
					]);
					break;
				case 'php_core':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Core',
						'Errors/php/Core/all',
						'Errors/php/Core/allWeb',
						'Errors/php/Core/allOther',
					]);
					break;
				case 'php_user':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/User',
						'Errors/php/User/all',
						'Errors/php/User/allWeb',
						'Errors/php/User/allOther',
					]);
					break;
				case 'php_other':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Other',
						'Errors/php/Other/all',
						'Errors/php/Other/allWeb',
						'Errors/php/Other/allOther',
					]);
					break;
				case 'php_fatal':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Fatal',
						'Errors/php/Fatal/all',
						'Errors/php/Fatal/allWeb',
						'Errors/php/Fatal/allOther',
					]);
					break;
				case 'php_uncaught':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Uncaught',
						'Errors/php/Uncaught/all',
						'Errors/php/Uncaught/allWeb',
						'Errors/php/Uncaught/allOther',
					]);
					break;
				case 'php_exception':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Exception',
						'Errors/php/Exception/all',
						'Errors/php/Exception/allWeb',
						'Errors/php/Exception/allOther',
					]);
					break;
				case 'php_errors':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'Errors/php/Error',
						'Errors/php/Error/all',
						'Errors/php/Error/allWeb',
						'Errors/php/Error/allOther',
					]);
					break;
				case 'response_time':
					$metrics_query_obj = array_merge( $metrics_query_obj, [
						'HttpDispatcher',
						'WebFrontend/QueueTime',
						'WebFrontend/Time',
						'WebTransaction',
						'WebTransactionTotalTime',
					]);
					break;
				default:
					# code...
					echo 'No metric found';
					break;
			}
		}

		if( empty($metrics_query_obj) ){
			exit('No metrics found');
		}

		$metrics_date_query = [
			"from={$this->date_range['week_start_system']}T00:00:00+00:00",
			"to={$this->date_range['week_end_system']}T23:59:59+00:00",
			"summarize=true",
		];
		
		$metrics_date_query = implode('&', $metrics_date_query);
		$metrics_query_obj = implode('&names[]=', $metrics_query_obj);
		// Add the first &names[]= to the beginning of the string.
		$metrics_query_obj = '&names[]=' . $metrics_query_obj;
		return $metrics_date_query . $metrics_query_obj;
	}
}

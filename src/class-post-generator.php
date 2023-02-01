<?php

declare(strict_types=1);

namespace MetricPoster;

use \MetricPoster\MetricFetcher;

class PostGenerator
{
	public string $template_file;
	public int $week;
	public int $year;
	public int $clientid;
	public $nr_metrics;

	function __construct( $file_path, $week, $year, $id, $nr_metrics )
	{
		print "In PostGenerator constructor\n";
		$this->template_file = $file_path; 
		$this->week = (int) $week; 
		$this->year = (int) $year; 
		$this->clientid = (int) $id;
		$this->nr_metrics = $nr_metrics;
	}

	function create()
	{
		$date_range = get_week_start_end((int) $this->week, $this->year);
		$fweek_title = $date_range['week_start_month'] . " " . $date_range['week_start_day'] . '-' . $date_range['week_end_day'] . ", " . $this->year;
		
		$nr_metrics = $this->nr_metrics->get_metrics_data();
		
		if( ! isset( $nr_metrics['metric_data']['metrics'] ) || empty($nr_metrics['metric_data']['metrics']) ) {
			exit('No metrics found');
		}

		foreach( $nr_metrics['metric_data']['metrics'] as $metric ){

			if( isset( $metric['timeslices'][0]['values']['value'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['value'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['average_value'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['average_call_time'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_call_time'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['call_count'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['call_count'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['error_count'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['total_call_time'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['total_call_time'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['total_exclusive_time'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['total_exclusive_time'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['total_time'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['total_time'] . "\n";
			}

			if( isset( $metric['timeslices'][0]['values']['average_response_time'] )){
				echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			}

			// switch( $metric['name'] ){
			// 	case 'Apdex':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['value'] . "\n";
			// 		break;
			// 	case 'EndUser/Apdex':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['value'] . "\n";
			// 		break;
			// 	case 'ResponseTimes':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebTransaction':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_call_time'] . "\n";
			// 		break;
			// 	case 'Errors/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Warning':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Warning/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Warning/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Warning/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Notice':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Notice/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Notice/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Notice/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Deprecated':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Deprecated/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Deprecated/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Deprecated/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Strict':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Strict/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Strict/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Strict/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Parse':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Parse/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Parse/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'Errors/php/Parse/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['error_count'] . "\n";
			// 		break;
			// 	case 'HttpDispatcher':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebFrontend/QueueTime':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/QueueTime/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/QueueTime/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/QueueTime/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/Time':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/Time/all':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/Time/allWeb':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebFrontend/Time/allOther':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_response_time'] . "\n";
			// 		break;
			// 	case 'WebTransaction':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebTransaction/Uri/*':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_call_time'] . "\n";
			// 		break;
			// 	case 'WebTransactionTotalTime':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebTransactionTotalTime/Uri/*':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebTransactionTotalTime/Uri/':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebTransactionTotalTime/Uri/404':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	case 'WebTransactionTotalTime/Uri/500':
			// 		echo $metric['name'] . ' ' . $metric['timeslices'][0]['values']['average_value'] . "\n";
			// 		break;
			// 	default:
			// 		echo $metric['name'] . "\n";
			// 		var_dump($metric['timeslices'][0]['values']);
			// 		break;
			// }
		}

		// $html = file_get_contents( $this->template_file );

		// $dom = new \DOMDocument();
		// $dom->loadHTML($html, LIBXML_NOERROR);

		// $dom->getElementById("p2-title")->nodeValue = "Weekly Metrics: $fweek_title";
		


		// // final output
		// echo $dom->saveHTML();

		exit("\npost created");

	}
}

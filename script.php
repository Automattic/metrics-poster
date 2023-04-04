<?php

declare(strict_types=1);

namespace MetricPoster;

use Dotenv\Dotenv;
use MetricPoster\NewRelicGQL;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load .env file.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// define global constants.
define('GUTENBERG_TPL', __DIR__ . '/gutenberg-templates');

// Get args from user.
$args = getopt('', ['week:', 'clientid:', 'id:', 'metrics:']);

// Bail early for any missing args.
if (!isset($args['week'])) {
	exit("Missing week value. .i.e script.php --week 51\n");
}

// Set year to current year if not set.
$year = !isset($args['year']) ? date('Y') : $args['year'];

$client_id = '';

if (!isset($args['clientid'])) {

	if (isset($args['id'])) {
		$client_id = $args['id'];
	}else{
		if(isset($_ENV['NEW_RELIC_ACCOUNT_ID'])){
			$client_id = $_ENV['NEW_RELIC_ACCOUNT_ID'];
		}else{
			exit("Missing client id value. .i.e script.php --week 51 --id 368\n");
		}
	}

} else {
	$client_id = $args['clientid'];
}

$default_metrics = 'apm_summary';

// Get metric type options from user.
if( isset( $args['metrics'] ) ) {

	if( $args['metrics'] === 'all' ){
		$args['metrics'] = $default_metrics;
	}

	// Fetch metrics from NewRelic and build metric object for DI.
	$nr_metrics = new NewRelicGQL( (int) $args['week'], (int) $year, (int) $client_id, $args['metrics'] );
	$pg = new PostGenerator( GUTENBERG_TPL . '/post.tpl.html', (int) $args['week'], (int) $year, (int) $client_id, $nr_metrics );
	$pg->create_post();
} else {
	exit("Missing metric type. .i.e script.php --week 51 --clientid 368 --metrics $default_metrics\n");
}

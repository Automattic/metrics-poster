<?php

declare(strict_types=1);

namespace MetricPoster;

use Dotenv\Dotenv;
use MetricPoster\NewRelicGQL;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

$args = getopt('', ['week:', 'clientid:', 'metrics:']);

// Bail early for any missing args.
if (!isset($args['week'])) {
	exit("Missing week value. .i.e script.php --week 51\n");
}

// Set year to current year if not set.
$year = !isset($args['year']) ? date('Y') : $args['year'];

if (!isset($args['clientid'])) {
	exit("Missing client id value. .i.e script.php --week 51 --clientid 368\n");
}

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$default_metrics = 'apdex,slowest_transactions,php_errors,php_warnings,php_notices,php_deprecated,php_strict,php_recoverable,php_core,php_user,php_other,php_fatal,php_uncaught,php_exception,php_slowest,php_slowest_db,php_slowest_external,response_time';

// Get metric type options from user.
if( isset( $args['metrics'] ) ) {

	if( $args['metrics'] === 'all' ){
		$args['metrics'] = $default_metrics;
	}

	// Fetch metrics from NewRelic and build metric object for DI.
	// TODO: Replace hard coded year.
	$nr_metrics = new NewRelicGQL( $args['week'], $year, $args['clientid'], $args['metrics'] );
	// var_dump( $nr_metrics->get_apm_summary() );
	// var_dump( $nr_metrics->get_top_404s() );
	// var_dump( $nr_metrics->get_top_500s() );

	$pg = new PostGenerator( __DIR__ . '/post.tpl.html', $args['week'], $year, $args['clientid'], $nr_metrics->get_top_404s() );
	$pg->create();
	exit( 'Done' );
} else {
	exit("Missing metric type. .i.e script.php --week 51 --clientid 368 --metrics $default_metrics\n");
}

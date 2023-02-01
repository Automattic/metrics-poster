<?php

declare(strict_types=1);

namespace MetricPoster;

use Dotenv\Dotenv;
use MetricPoster\NewRelic;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

$args = getopt('', ['week:', 'clientid:', 'metrics:']);

// Bail early for any missing args.
if (!isset($args['week'])) {
	exit("Missing week value. .i.e script.php --week 51\n");
}

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
	$nr_metrics = new NewRelic( $args['week'], 2023, $args['clientid'], $args['metrics'] );
	$pg = new PostGenerator( __DIR__ . '/post.tpl.html', $args['week'], 2023, $args['clientid'], $nr_metrics );
	$pg->create();
	exit( 'Done' );
} else {
	exit("Missing metric type. .i.e script.php --week 51 --clientid 368 --metrics $default_metrics\n");
}

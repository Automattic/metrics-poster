<?php

declare(strict_types=1);

namespace MetricPoster;

use Dotenv\Dotenv;
use MetricPoster\NewRelicGQL;
use MetricPoster\Utils;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load .env file.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Define global constants.
define('GUTENBERG_TPL', __DIR__ . '/gutenberg-templates');
define('DEV_ENV', $_ENV['ENV'] ?? 'dev');

// Get arguments from user.
$args = getopt('', ['week:', 'clientid:', 'id:', 'metrics:', 'year:', 'title:', 'generateHTML:', 'facet:']);

$week = $args['week'] ?? get_prev_week_number();

if (isset($args['title']) && $args['title'] === 'true') {
	$show_headings = true;
}else {
	$show_headings = false;
}

if (isset($args['facet']) && $args['facet'] === 'true') {
	$facet = true;
}else {
	$facet = false;
}

if (isset($args['generateHTML']) && $args['generateHTML'] === 'true') {
	$generateHTML = true;
}else {
	$generateHTML = false;
}

// Set year to current year if not set.
$year = $args['year'] ?? date('Y');

// Determine client ID.
$client_id = $args['clientid'] ?? $args['id'] ?? $_ENV['NEW_RELIC_ACCOUNT_ID'] ?? null;

if (!$client_id) {
	exit("Missing client ID value. .i.e script.php --week 51 --id 368\n");
}

// Determine metrics to fetch.
$metrics = $args['metrics'] ?? 'apm_summary';

if ($metrics === 'all') {
	$metrics = 'apm_summary';
}

// Fetch metrics from NewRelic and build metric object for DI.
$nr_metrics = new NewRelicGQL((int) $week, (int) $year, (int) $client_id, $metrics, (bool) $facet);

// generate p2 post if generateHTML is set.
if ($generateHTML) {
	$pg = new PostGenerator(GUTENBERG_TPL . '/post.tpl.html', (int) $week, (int) $year, (int) $client_id, $nr_metrics, (bool) $show_headings);
	$pg->create_post();
}else{
	// Generate JSON file.
	if( is_array($nr_metrics->results) ) {
		// $utils = new Utils( (int) $week, $nr_metrics->results );
		// $utils->zapier_webhook_trigger();
		// convert to json and echo.
		echo json_encode($nr_metrics->results);
		exit;
	}
}

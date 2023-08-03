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

// Define global constants.
define('GUTENBERG_TPL', __DIR__ . '/gutenberg-templates');
define('DEV_ENV', $_ENV['ENV'] ?? 'dev');

// Get arguments from user.
$args = getopt('', ['week:', 'clientid:', 'id:', 'metrics:', 'year:', 'title:', 'generateHTML:', 'facet:', 'app_name:']);

$week = $args['week'] ?? get_prev_week_number();
$show_headings = isset($args['title']) && $args['title'] === 'true';
$facet = isset($args['facet']) && $args['facet'] === 'true';
$generateHTML = isset($args['generateHTML']) && $args['generateHTML'] === 'true';
$app_name = $args['app_name'] ?? 'app';

// Set year to the current year if not set.
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

// Fetch metrics from NewRelic and build a metric object for DI.
$nr_metrics = new NewRelicGQL((int) $week, (int) $year, (int) $client_id, $metrics, (bool) $facet);
$metric_results = $nr_metrics->get_results();
// Generate p2 post if generateHTML is set.
if ($generateHTML) {
	$pg = new PostGenerator(GUTENBERG_TPL . '/post.tpl.html', (int) $week, (int) $year, (int) $client_id, $metric_results, (bool) $show_headings, (string) $app_name);
	$pg->create_post();
} else {
	// Generate JSON file.
	if (is_array($metric_results)) {
		// $utils = new Utils((int) $week, $metric_results->results);
		// $utils->zapier_webhook_trigger();
		// Convert to JSON and echo.
		echo json_encode($metric_results, JSON_PRETTY_PRINT);
	}
	exit;
}

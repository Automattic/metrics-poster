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

// Get arguments from user.
$args = getopt('', ['week:', 'clientid:', 'id:', 'metrics:', 'year:', 'title:']);

// Ensure required arguments are present.
if (!isset($args['week'])) {
	exit("Missing week value. .i.e script.php --week 51\n");
}

if (!isset($args['title'])) {
	$args['title'] = true;
}else {
	$args['title'] = false;
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
$nr_metrics = new NewRelicGQL((int) $args['week'], (int) $year, (int) $client_id, $metrics);
$pg = new PostGenerator(GUTENBERG_TPL . '/post.tpl.html', (int) $args['week'], (int) $year, (int) $client_id, $nr_metrics, (bool) $args['title']);
$pg->create_post();

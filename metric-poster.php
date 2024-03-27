<?php

/**
 * Plugin Name: Metric Poster
 * Plugin URI: https://github.com/Automattic/metrics-poster
 * Description: A plugin to generate a post from a template and post it to a P2 site.
 * Version: 1.0.23
 */

declare(strict_types=1);

namespace MetricPoster;

// cant access this directly.
if (!defined('ABSPATH')) {
	exit;
}

use Dotenv\Dotenv;
use MetricPoster\UI\SettingsPage;
use MetricPoster\CronSetup;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load .env file.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Define global constants.
define('GUTENBERG_TPL', __DIR__ . '/gutenberg-templates');
define('DEV_ENV', $_ENV['ENV'] ?? 'dev');


// init hook.
\add_action('init', function () {
	\register_post_type('metric_posts', [
		'labels' => [
			'name' => __('Metric Post Objects'),
			'singular_name' => __('Metric Post Object'),
			'menu_name' => __('Metric Post Objects (debugging only)'),
		],
		'public' => true,
		'menu_position' => 5,
		'has_archive' => true, 
		'show_in_rest' => true,
		'rewrite' => ['slug' => 'metric_posts'],
		'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields', 'revisions', 'page-attributes', 'post-formats'],
	]);

	$s = new SettingsPage();
	$s->run();

	$cron = new CronSetup();
	$cron->run();
});

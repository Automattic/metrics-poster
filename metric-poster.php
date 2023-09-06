<?php

/**
 * Plugin Name: Metric Poster
 * Plugin URI:
 * Description: A plugin to generate a post from a template and post it to a P2 site.
 * Version: 1.0.0
 */

declare(strict_types=1);

namespace MetricPoster;

// cant access this directly.
if (!defined('ABSPATH')) {
	exit;
}

use Dotenv\Dotenv;
use MetricPoster\UI\SettingsPage;

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
	$s = new SettingsPage();
	$s->run();
});

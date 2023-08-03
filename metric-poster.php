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
use MetricPoster\NewRelicGQL;
use MetricPoster\AppModel;

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load .env file.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Define global constants.
define('GUTENBERG_TPL', __DIR__ . '/gutenberg-templates');
define('DEV_ENV', $_ENV['ENV'] ?? 'dev');

// create a plugin options page with sub settings page.
\add_action('admin_menu', __NAMESPACE__ . '\\metric_poster_settings');
function metric_poster_settings()
{
	\add_menu_page('Metric Poster', 'Metric Poster', 'manage_options', 'metric-poster', __NAMESPACE__ . '\\metric_poster_options', 'dashicons-chart-bar', 6);
	\add_submenu_page('metric-poster', 'Metric Poster', 'Main', 'manage_options', 'metric-poster', __NAMESPACE__ . '\\metric_poster_options');
	\add_submenu_page('metric-poster', 'Metric Poster Settings', 'Settings', 'manage_options', 'metric-poster-help', __NAMESPACE__ . '\\metric_poster_settings_page');
}

function metric_poster_settings_page()
{

	// check user capabilities.
	if (!\current_user_can('manage_options')) {
		return;
	}

	// on save, update options.
	if (isset($_POST['metric_poster_options']) && isset($_POST['metric_poster_options']['app_id'])) {
		$metric_poster_options = \get_option('metric_poster_options[apps]', array());

		$app_id = $_POST['metric_poster_options']['app_id'];

		$metric_poster_options[$app_id] = [];
		
		$metric_poster_options[$app_id]['app_name'] = $_POST['metric_poster_options']['app_name'];
		$metric_poster_options[$app_id]['nr_id'] = $_POST['metric_poster_options']['nr_id'];
		$metric_poster_options[$app_id]['nr_browser_guid'] = $_POST['metric_poster_options']['nr_browser_guid'];
		$metric_poster_options[$app_id]['nr_app_guid'] = $_POST['metric_poster_options']['nr_app_guid'];
		\update_option('metric_poster_options[apps]', $metric_poster_options);
	}
	
	?>
	<div class="wrap">
		<h1>Metric Poster Settings</h1>
		<style>
			#metric--settings {
				width: 100%;
				border-collapse: collapse;
				margin-bottom: 40px;
			}

			#metric--settings th,
			#metric--settings td {
				border: 1px solid #ccc;
				padding: 5px;
			}

			#metric--settings th {
				text-align: left;
			}
		</style>
		<table id="metric--settings">
			<thead>
				<tr>
					<th>App ID</th>
					<th>App Name</th>
					<th>NR ID</th>
					<th>NR Browser GUID</th>
					<th>NR App GUID</th>
				</tr>
			</thead>
			<tbody>
				<!-- for each item in option, display row -->
				<?php
				$metric_poster_options = \get_option('metric_poster_options[apps]');
				foreach ($metric_poster_options as $key => $value) {
					?>
					<tr>
						<td>
							<?php echo $key; ?>
						</td>
						<td>
							<?php echo $value['app_name']; ?>
						</td>
						<td>
							<?php echo $value['nr_id']; ?>
						</td>
						<td>
							<?php echo $value['nr_browser_guid']; ?>
						</td>
						<td>
							<?php echo $value['nr_app_guid']; ?>
						</td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
		<form method="post" action="">
			<?php
			\settings_fields('metric_poster_options');
			\do_settings_sections('metric_poster_options'); ?>

			<table>
				<thead>
					<tr>
						<th>App Name</th>
						<th>App ID</th>
						<th>NR ID</th>
						<th>NR Browser GUID</th>
						<th>NR App GUID</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<input type="text" name="metric_poster_options[app_name]">
						</td>
						<td>
							<input type="text" name="metric_poster_options[app_id]">
						</td>
						<td>
							<input type="text" name="metric_poster_options[nr_id]">
						</td>
						<td>
							<input type="text" name="metric_poster_options[nr_browser_guid]">
						</td>
						<td>
							<input type="text" name="metric_poster_options[nr_app_guid]">
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php
			\submit_button();
			?>
		</form>
	</div>
<?php
}



function metric_poster_options()
{

	if (!\current_user_can('manage_options')) {
		\wp_die('You do not have sufficient permissions to access this page.');
	}

	// on post check metric_poster_options nonce.
	if (isset($_POST['metric_poster_options_nonce'])) {
		// verify nonce.
		if (!\wp_verify_nonce($_POST['metric_poster_options_nonce'], 'metric_poster_options')) {
			\wp_die('Nonce verification failed.');
		}
	}

	// if $_POST['metric_poster_options'] is set, do stuff.
	if (isset($_POST['metric_poster_options'])) {
		// get metric_poster_options[app_name] value.
		$app_id = $_POST['metric_poster_options']['app_name'];
		$week = $_POST['metric_poster_options']['week'];

		// initialize AppModel
		$app_info = \get_option('metric_poster_options[apps]')[$app_id];
		$app_info = new AppModel($app_info['app_name'], $app_id, $app_info['nr_id'], $app_info['nr_browser_guid'], $app_info['nr_app_guid']);

		// create comma separated list of metrics from $_POST['metric_poster_options']['metrics'] array.
		$metrics = [];
		foreach ($_POST['metric_poster_options']['metrics'] as $metric) {
			switch($metric){
				case 'errors':
					$metrics []= 'error_count';
					$metrics []= 'errors';
					break;
				case 'warnings':
					$metrics []= 'warning_count';
					$metrics []= 'warnings';
					break;
				case 'cwv':
					$metrics []= 'cwv';
					$metrics []= 'cwv_chart';
					break;
				default:
					$metrics []= $metric;
			}
		}

		$show_headings = true;
		$facet = true;
		$year = date('Y');
		$metrics = implode(',', $metrics);
		$NR_ACCOUNT_ID = $app_info->get_nr_id();

		// Fetch metrics from NewRelic and build a metric object for DI.
		$nr_metrics = new NewRelicGQL($app_info, (int) $week, (int) $year, (int) $NR_ACCOUNT_ID, $metrics, (bool) $facet);
		$metric_results = $nr_metrics->get_results();
		$pg = new PostGenerator(GUTENBERG_TPL . '/post.tpl.html', (int) $week, (int) $year, $metric_results, (bool) $show_headings, (string) $app_info->get_app_name());
		$output_post = $pg->create_post();
}
	


	?>
	<div class="wrap">
		<h2>Metric Poster</h2>
		<form method="post" action="">
			<?php
			\settings_fields('metric_poster_options');
			\do_settings_sections('metric_poster_options');
			// do nonce field.
			\wp_nonce_field('metric_poster_options', 'metric_poster_options_nonce');
			?>
			<table class="form-table">
				<tr>
					<th scope="row">Application</th>
					<td>
						<select name="metric_poster_options[app_name]">							
							<!-- <option value="Macworld">Macworld</option>
							<option value="cio.com">cio.com</option> -->
							<!-- generate options from $metric_poster_options = \get_option('metric_poster_options[apps]'); -->
							<?php
							$metric_poster_options = \get_option('metric_poster_options[apps]');
							// echo placeholder option.
							echo '<option value="">Select an app</option>';
							foreach ($metric_poster_options as $key => $value) {
								?>
								<option value="<?php echo $key; ?>"><?php echo $value['app_name']; ?></option>
							<?php
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Week</th>
					<td>
						<select name="metric_poster_options[week]">							
							<option value="<?php echo get_prev_week_number();?>"><?php echo get_prev_week_number();?></option>
							<option value="<?php echo date('W') - 2;?>"><?php echo date('W') - 2;?></option>
						</select>
					</td>
				</tr>
				<style>
					input[type="checkbox"] {
						margin-right: 4px;
					}
					input[type="checkbox"] + label {
						margin-right: 8px;
					}
				</style>
				<tr>
					<th scope="row">Metrics</th>
					<td>
						<input type="checkbox" name="metric_poster_options[metrics][]" value="errors" <?php \checked(1, true, true); ?> />
						<label for="metric_poster_options[metrics][]">PHP Errors</label>

						<input type="checkbox" name="metric_poster_options[metrics][]" value="warnings" <?php \checked(1, true, true); ?> />
						<label for="metric_poster_options[metrics][]">PHP Warnings</label>

						<input type="checkbox" name="metric_poster_options[metrics][]" value="transactions" <?php \checked(1, true, true); ?> />
						<label for="metric_poster_options[metrics][]">Top Slow Transactions</label>

						<input type="checkbox" name="metric_poster_options[metrics][]" value="cwv" <?php \checked(1, true, true); ?> />
						<label for="metric_poster_options[metrics][]">CWV Chart</label>

						<input type="checkbox" name="metric_poster_options[metrics][]" value="404s" <?php \checked(1, false, false); ?> />
						<label for="metric_poster_options[metrics][]">404 Errors</label>

						<input type="checkbox" name="metric_poster_options[metrics][]" value="500s" <?php \checked(1, false, false); ?> />
						<label for="metric_poster_options[metrics][]">500 Errors</label>
					</td>
				</tr>

				<?php 
				// if $output_post is set, show the results.
				if (isset($output_post)) {
					?>
					<tr>
						<th scope="row">Output</th>
						<td>
							<button type="button" id="copy_to_clipboard">Copy to Clipboard</button>
							<pre><textarea id="p2_output" rows="20" cols="100" name="metric_poster_options[output_post]"><?php echo \esc_attr($output_post); ?></textarea></pre>
						</td>
					</tr>
					<!-- script for copy to clipboard on click event for element #p2_output -->
					<script>
						// on dom ready
						document.addEventListener('DOMContentLoaded', function() {
							// on click of #copy_to_clipboard
							document.getElementById('copy_to_clipboard').addEventListener('click', function() {
								// select the text in #p2_output
								document.getElementById('p2_output').select();
								// copy the text to the clipboard
								document.execCommand('copy');
								alert('copied to clipboard');
							});
						});
					</script>
					<?php
				}
				?>
			</table>
			<?php \submit_button('Get Metrics'); ?>
		</form>
	</div>
<?php	
}

\add_action('admin_init', __NAMESPACE__ . '\\metric_poster_register_settings');
function metric_poster_register_settings()
{
	\register_setting('metric_poster_options', 'metric_results');
}



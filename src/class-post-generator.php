<?php

declare(strict_types=1);

namespace MetricPoster;

use function MetricPoster\Utils\zapier_webhook_trigger;
use \DOMDocument;

class PostGenerator
{
	public string $template_file;
	public int $week;
	public int $year;
	public int $clientid;
	public $nr_metrics;
	public bool $show_header_footer;
	public string $app_name;

	public function __construct(string $file_path, int $week, int $year, $nr_metrics, bool $show_header_footer, string $app_name = 'test app')
	{
		$this->template_file = $file_path;
		$this->week = $week;
		$this->year = $year;
		$this->nr_metrics = $nr_metrics;

		// TODO: Should be more descriptive as it uses template files and not just headings.
		$this->show_header_footer = $show_header_footer;
		$this->app_name = $app_name;
	}

	public function create_post()
	{
		$nr_metrics = $this->nr_metrics;

		if (!isset($nr_metrics)) {
			exit('No metrics found');
		}

		// Create the post title with the week and year.
		$date_range = get_week_start_end($this->week, $this->year);
		$fweek_title = sprintf('%s %s-%s, %s', $date_range['week_start_month'], $date_range['week_start_day'], $date_range['week_end_day'], $this->year);

		// Create the main p2 DOMDocument.
		$dom = new DOMDocument();

		$content_dom = new DOMDocument();
		$content_dom->loadHTML('<body></body>');
		$content_body = $content_dom->getElementsByTagName('body')->item(0);

		if ($this->show_header_footer) {
			// Load the template file into a DOMDocument.
			$html = file_get_contents($this->template_file);
			$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
		
			dom_string_replace($dom, '{{week}}', $this->week);
			dom_string_replace($dom, '{{date_range}}', $fweek_title);
			dom_string_replace($dom, '{{app_name}}', $this->app_name);
			dom_string_replace($dom, '{{current_year}}', $this->year);
		} else{
			// file_get_contents($this->template_file);
			$html = file_get_contents(GUTENBERG_TPL . '/post-no-header-footers.html');
			$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
		}

		// Create the post content with the metrics.
		foreach ($nr_metrics as $metric_key => $metric) {
			switch ($metric_key) {
				case 'cwv':

					$this->create_p2_headings($content_dom, 'h2', 'Core Web Vitals (CWV)');

					$m = $metric['data']['actor']['account']['nrql']['results'] ?? [];
					$cwv_template_html = $this->create_cwv_html($m);
					$importedNode = $content_dom->importNode($cwv_template_html, true);
					
					// create comment.
					$comment = $content_dom->createComment(' wp:columns {"verticalAlignment":null,"style":{"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"}}},"fontSize":"large"} ');
					
					$content_body->appendChild($comment);

					// append importedNode to dom.
					$content_body->appendChild($importedNode);

					// create closing comment and append to dom.
					$comment = $content_dom->createComment(' /wp:columns ');
					$content_body->appendChild($comment);

					break;
				case 'cwv_extended':
				case 'cwv_mobile_extended':

					$metric_names = [
						'cwv_extended' => 'Core Web Vitals Week Over Week',
						'cwv_mobile_extended' => 'Core Web Vitals (Mobile) Week Over Week',
					];

					$caption_text = "The period of weekly metrics collection is Sunday to Saturday";
					$heading_text = "{$metric_names[$metric_key]}";

					$this->create_p2_headings($content_dom, 'h2', $heading_text);

					// get postmeta with meta_key $metric_names[$metric_key]
					$metric_meta_value = \get_post_meta( $metric->ID, $metric_key, true );
					
					if (! $metric_meta_value ) {
						// wp error log
						error_log("No postmeta found for {$metric_key} in post {$metric->ID}");
						
						continue;
					}

					// unserialize $metric_meta_value.
					$metric_array = unserialize($metric_meta_value) ?? [];

					// if cwv_mobile_extended, get last item from $metric_array.
					// if ($metric_key == 'cwv_mobile_extended') {
					// 	$metric_array = end($metric_array);
					// }

					// create create_html_table.
					$table = $this->create_html_table($content_dom, $metric_array, "{$metric_key}");

					$caption = $content_dom->createElement('figcaption', $caption_text);
					$caption->setAttribute('class', 'wp-element-caption');
					$table->appendChild($caption);

					// create comment.
					$comment = $content_dom->createComment(" wp:table ");
					$content_body->appendChild($comment);

					// append table to dom.
					$content_body->appendChild($table);

					// create closing comment and append to dom.
					$comment = $content_dom->createComment(" /wp:table ");
					$content_body->appendChild($comment);
					break;
				case '404s':
				case '500s':
				case 'errors':
				case 'warnings':
					$m = $metric['data']['actor']['account']['nrql']['results'] ?? [];

					// skip if $m is empty or not an array.
					if (empty($m) || ! is_array($m)) {
						continue;
					}

					
					$metric_names = [
						'404s' => '404s',
						'500s' => '500s',
						'errors' => 'Errors',
						'warnings' => 'Warnings'
					];
					
					$heading_text = "Top {$metric_names[$metric_key]}";

					$this->create_p2_headings($content_dom, 'h2', $heading_text);
					$table = $this->create_table($content_dom, $m, "{$metric_key}", 'Count');
					
					$caption_text = "Counts and listing of most frequently occurring {$metric_names[$metric_key]}";
					$caption = $content_dom->createElement('figcaption', $caption_text);
					$caption->setAttribute('class', 'wp-element-caption');
					$table->appendChild($caption);

					// create comment.
					$comment = $content_dom->createComment(" wp:table ");
					$content_body->appendChild($comment);

					// append table to dom.
					$content_body->appendChild($table);

					// create closing comment and append to dom.
					$comment = $content_dom->createComment(" /wp:table ");
					$content_body->appendChild($comment);
					break;
				case 'error_count':
				case 'warning_count':
				case 'jetpack_pageviews':
					// $m = $metric['data']['actor']['account']['nrql']['results'] ?? [];

					$metric_names = [
						'error_count' => 'PHP Errors',
						'warning_count' => 'PHP Warnings',
						'jetpack_pageviews' => 'Jetpack Page Views'
					];

					$caption_text = "The period of weekly metrics collection is Sunday to Saturday";
					$heading_text = "{$metric_names[$metric_key]}";

					$this->create_p2_headings($content_dom, 'h2', $heading_text);

					// get postmeta with meta_key $metric_names[$metric_key]
					$metric_meta_value = \get_post_meta( $metric->ID, $metric_key, true );
					
					// unserialize $metric_meta_value.
					$metric_array = unserialize($metric_meta_value) ?? [];

					// TODO: try to remove elements after key $this->week.

					// create create_html_table.
					$table = $this->create_html_table($content_dom, $metric_array, "{$metric_names[$metric_key]}");

					// $table = $this->create_table($content_dom, $m, "", "Week {$this->week}", $metric_names[$metric_key]);

					$caption = $content_dom->createElement('figcaption', $caption_text);
					$caption->setAttribute('class', 'wp-element-caption');
					$table->appendChild($caption);

					// create comment.
					$comment = $content_dom->createComment(" wp:table ");
					$content_body->appendChild($comment);

					// append table to dom.
					$content_body->appendChild($table);

					// create closing comment and append to dom.
					$comment = $content_dom->createComment(" /wp:table ");
					$content_body->appendChild($comment);
					break;
				case 'transactions':
					$m = $metric['data']['actor']['account']['nrql']['results'] ?? [];
					$table = $this->create_table($content_dom, $m, "Top {$metric_key}", 'Average Duration (ms)', 'transaction');

					$this->create_p2_headings($content_dom, 'h2', 'Top Slow Transactions');

					// create comment.
					$comment = $content_dom->createComment(" wp:table ");
					$content_body->appendChild($comment);

					// append table to dom.
					$content_body->appendChild($table);

					// create closing comment and append to dom.
					$comment = $content_dom->createComment(" /wp:table ");
					$content_body->appendChild($comment);
					break;
				case 'cwv_chart':
					$m = $metric['data']['actor']['account']['nrql']['results'] ?? [];
					$table = $this->create_big_table($content_dom, $m, ['Page URL', 'CLS', 'INP', 'LCP', 'Page Views']);

					// create comment.
					$comment = $content_dom->createComment(" wp:table ");
					$content_body->appendChild($comment);

					// append table to dom.
					$content_body->appendChild($table);

					// create closing comment and append to dom.
					$comment = $content_dom->createComment(" /wp:table ");
					$content_body->appendChild($comment);
					break;
				default:
					var_dump($metric);
					break;
			}
		}

		// get body of $content_dom.
		$content_dom = $content_dom->getElementsByTagName('body')->item(0);
		$content_body = $dom->importNode($content_dom, true);

		// find and replace string {{content_body}} in &$dom with $content_html.
		$this->replace_content_body($dom, $content_body);
		
		// Save the DOMDocument as HTML.
		$final_html_markup = $dom->saveHTML();
		
		// Required for smooth content body import.
		$final_html_markup = str_replace(['<body>', '</body>'], '', $final_html_markup);
		$final_html_markup = str_replace('<p><!--', '<!--', $final_html_markup);
		$final_html_markup = str_replace('--></p>', '-->', $final_html_markup);
		$final_html_markup = preg_replace('/<p[^>]*>([\s]|&nbsp;)*<\/p>/', '', $final_html_markup);

		// if ZAPIER env is set, send the post to Zapier.
		if (isset($_ENV['ZAPIER'])) {
			$title = "Week {$this->week} Metrics for {$this->app_name}: $fweek_title";
			zapier_webhook_trigger($_ENV['P2_DOMAIN'], $title, $final_html_markup);
			exit("\np2 posted by Zapier!\n");
		}

		return $final_html_markup;

	}

	public function replace_content_body(&$dom, $content_html)
	{
		$xpath = new \DOMXPath($dom);
		
		// Find string NODE {{content_body}} in $dom.
		$nodes = $xpath->query('//text()[contains(., "{{content_body}}")]');

		foreach ($nodes as $node) {
			$node->parentNode->replaceChild($content_html, $node);
		}

	}

	// function to create a table from an array.
	public function create_html_table(&$dom, $metric_arr, $metric_name = 'Errors' ) {
		$table = $dom->createElement('table');
		$thead = $dom->createElement('thead');
		$tbody = $dom->createElement('tbody');

		// Create header row.
		$tr = $dom->createElement('tr');

		// empty header cell.
		$th = $dom->createElement('th', '');
		$tr->appendChild($th);

		// for each column in $metric_arr[0], create a header column in the table.
		foreach ($metric_arr as $week => $value) {
			$th = $dom->createElement('th', "Week {$week}");
			$tr->appendChild($th);
		}

		$thead->appendChild($tr);
		$table->appendChild($thead);

		if ( $metric_name != 'cwv_extended' && $metric_name != 'cwv_mobile_extended') {
			// for each row in $metric_arr, create a row in the table.
			$tr = $dom->createElement('tr');

			// header cell.
			$td = $dom->createElement('td', $metric_name);
			$tr->appendChild($td);
			foreach ($metric_arr as $weekval ) {

				// if $weekval is numeric, format it with thousand seprator.
				if (is_numeric($weekval)) {
					$weekval = number_format($weekval);
				}

				$td = $dom->createElement('td', "{$weekval}");
				$tr->appendChild($td);

				$tbody->appendChild($tr);
			}

			$tr = $dom->createElement('tr');
			$td = $dom->createElement('td', 'Change');
			$tr->appendChild($td);

			foreach ($metric_arr as $key => $val) {

				// get previous item in array.
				$previous_column = getPrevKey($key, $metric_arr);
				$previous_column = $metric_arr[$previous_column] ?? null;
				
				// check if previous $column exists.
				if ($previous_column !== null) {

					if ( ! is_numeric($previous_column) ) {

						// try to remove units from $val and $previous_column.
						$val = $this->convert_back_to_original_value($val);
						$previous_column = $this->convert_back_to_original_value($previous_column);
					}

					if (is_numeric($previous_column) && $previous_column != 0) {
						$change = round((($val - $previous_column) / $previous_column) * 100, 2);
					} else if (is_numeric($previous_column) && $previous_column == 0) {
						$change = 100;
					} 
					else {
						$change = 0;
					}
					
					$td = $dom->createElement('td', "{$change}%");

				} else {
					$td = $dom->createElement('td', "0%");
				}

				$tr->appendChild($td);
			}

			// close change row.
			$tbody->appendChild($tr);

		} else {

			// get last item from $metric_arr for headings.
			$last_item = end($metric_arr);

			// if is cwv_mobile_extended, get last item from $last_item again.
			if ($metric_name == 'cwv_mobile_extended') {
				$last_item = end($last_item);
			}
			
			foreach ($last_item as $cwv_name => $v) { 
				$tr = $dom->createElement('tr');

				$fcwv_name = str_replace('percentile.', '', $cwv_name);

				$td = $dom->createElement('td', $fcwv_name);
				$tr->appendChild($td);

				$cwv_unit = $this->format_cwv_metric($fcwv_name);

				foreach ($metric_arr as $week => $cwv) {
					if ($metric_name == 'cwv_mobile_extended') {
						$cwv = end($cwv);
					}

					// if $cwv[$cwv_name] is numeric, format it to two decimal places.
					$cwv_value = $cwv[$cwv_name]['75'] ?? 0;
					if (is_numeric($cwv_value)) {
						$cwv_value = number_format($cwv_value, 2);
					}

					$td = $dom->createElement('td', "{$cwv_value} {$cwv_unit}");

					// add inline style to td.
					$td->setAttribute('style', $this->metric_value_color($cwv_value, $fcwv_name));

					$tr->appendChild($td);
				}

				$tbody->appendChild($tr);
			}
		}

		$table->appendChild($tbody);

		// Create figure element and append the table.
		$figure = $dom->createElement('figure');
		$figure->setAttribute('class', 'wp-block-table');
		$figure->appendChild($table);

		return $figure;
	}

	public function create_table(&$dom, $nr_metrics, $header1 = 'Metric', $header2 = 'Count', $transaction_type = 'facet')
	{
		// Create a DOMDocument 2x2 table with a header row and append to $dom.
		$table = $dom->createElement('table');
		$thead = $dom->createElement('thead');
		$tbody = $dom->createElement('tbody');

		// Create header row.
		$tr = $dom->createElement('tr');
		$th1 = $dom->createElement('th', $header1);
		$th2 = $dom->createElement('th', $header2);
		$tr->appendChild($th1);
		$tr->appendChild($th2);

		$thead->appendChild($tr);
		$table->appendChild($thead);

		// Append rows to table.
		foreach ($nr_metrics as $metric) {
			$tr = $dom->createElement('tr');

			// sanitize and escape $metric['facet'] to prevent XSS.
			if( isset($metric['facet']) ) {
				$sanitized_metric = htmlspecialchars($metric['facet']);
			}

			// Format counts or durations to be human-readable.
			$formatted_value = '';

			switch ($transaction_type) {
				case 'Errors':
				case 'Warnings':
					$metric_name = $transaction_type;
					$formatted_value = is_numeric($metric['count']) ? number_format($metric['count']) : 0;

					// create table cells and append to row.					
					$td = $dom->createElement('td', $metric_name);
					$tr->appendChild($td);
					$td = $dom->createElement('td', "{$formatted_value}");
					$tr->appendChild($td);
					$tbody->appendChild($tr);
					
					$tr = $dom->createElement('tr');
					$td = $dom->createElement('td', 'Change');
					$tr->appendChild($td);
					$td = $dom->createElement('td', "0%");
					$tr->appendChild($td);
					$tbody->appendChild($tr);
					break;
				case 'transaction':
					$formatted_value = $metric['average.duration'] ?? 0;
					$formatted_value = number_format($formatted_value, 2);

					// create table cells and append to row.
					$td = $dom->createElement('td', $sanitized_metric);
					$tr->appendChild($td);
					$td = $dom->createElement('td', "{$formatted_value}");
					$tr->appendChild($td);
					$tbody->appendChild($tr);
					break;
				default:
					$formatted_value = is_numeric($metric['count']) ? number_format($metric['count']) : 0;

					// create table cells and append to row.
					$td = $dom->createElement('td', $sanitized_metric);
					$tr->appendChild($td);
					$td = $dom->createElement('td', "{$formatted_value}");
					$tr->appendChild($td);
					$tbody->appendChild($tr);
					break;
			}
		}

		$table->appendChild($tbody);

		// Create figure element and append the table.
		$figure = $dom->createElement('figure');
		$figure->setAttribute('class', 'wp-block-table');
		$figure->appendChild($table);

		return $figure;
	}

	public function metric_value_color($value, $metric_name = '')
	{
		// colors
		$vivid_green_cyan = '#00D084';
		$luminous_vivid_amber = '#FFC400';
		$vivid_red = '#CF2E2E';

		// text color
		$black = 'black';
		$white = 'white';

		// default color.
		$text_color = $black;

		switch ($metric_name) {
			case 'CLS':
			case 'cumulativeLayoutShift':
				if ($value <= 0.1) {
					$color_hex = $vivid_green_cyan;
				} else if ($value > 0.25) {
					$color_hex = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'FCP':
			case 'firstContentfulPaint':
				if ($value <= 2.5) {
					$color_hex = $vivid_green_cyan;
				} else if ($value > 4) {
					$color_hex = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'FID':
			case 'firstInputDelay':
				if ($value <= 0.2) {
					$color_hex = $vivid_green_cyan;
				} else if ($value > 0.5) {
					$color_hex = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'LCP':
			case 'largestContentfulPaint':
				if ($value <= 2.5) {
					$color_hex = $vivid_green_cyan;
				} else if ($value > 4) {
					$color_hex = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'FP':
			case 'firstPaint':
				if ($value <= 2.5) {
					$color_hex = $vivid_green_cyan;
				} else if ($value > 4) {
					$color_hex = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'INP':
			case 'interactionToNextPaint':
				if ($value <= 0.2) {
					$color_hex = $vivid_green_cyan;
				} else if ($value > 0.5) {
					$color_hex = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			default:
				$color_hex = '';
				break;
		}
		
		if (!empty($color_hex)) {
			return "background-color: {$color_hex}; color: {$text_color};";
		} else {
			return "";
		}
		
	}

	public function format_cwv_metric( $metric )
	{
		// switch case for each metric value to include time unit.
		switch ($metric) {
			case 'cumulativeLayoutShift':
				$unit = 's';
				break;
			case 'firstContentfulPaint':
				$unit = 's';
				break;
			case 'firstInputDelay':
				$unit = 'ms';
				break;
			case 'largestContentfulPaint':
				$unit = 's';
				break;
			case 'firstPaint':
				$unit = 's';
				break;
			case 'interactionToNextPaint':
				$unit = 's';
				break;
			default:
				$unit = '';
				break;
		}

		return $unit;
	}


	public function create_big_table(&$dom, $nr_metrics, $headers = [], $transaction_type = 'facet')
	{
		$table = $dom->createElement('table');
		$thead = $dom->createElement('thead');
		$tbody = $dom->createElement('tbody');

		// Create header row.
		$tr = $dom->createElement('tr');

		foreach ($headers as $header) {
			$th = $dom->createElement('th', $header);
			$tr->appendChild($th);
		}
		
		$thead->appendChild($tr);
		$table->appendChild($thead);

		// Append rows to table.
		foreach ($nr_metrics as $metric) {
			$tr = $dom->createElement('tr');

			// check if metric is an array.
			if (! is_array($metric)){
				return;
			}

			$previous_value = null;
			$counter_loop = 1;
			foreach ($metric as $key => $value) {

				// if counter is greater than headers, continue.
				if ($counter_loop > count($headers)) {
					continue;
				}

				// return if value is the same as the previous value.
				if ($value === $previous_value) {
					continue;
				} else {
					$previous_value = $value;
				}

				// if value is array with one item, get that item.
				if (is_array($value) && count($value) === 1) {
					// get first item in array, regardless of key.
					$value = array_values($value)[0];
				}

				// if value is decimal number, format it.
				if (is_numeric($value) && strpos("{$value}", '.') !== false) {
					$value = number_format($value, 2);
				}

				// else if value is number, format it with thousand seprator.
				elseif (is_numeric($value)) {
					$value = number_format($value);
				}
	
				// Create table cells and append to row.
				$td = $dom->createElement('td', "{$value}");

				$inline_style = $this->metric_value_color($value, $key);

				$td->setAttribute('style', $inline_style);

				$tr->appendChild($td);
				$counter_loop++;
			}

			$tbody->appendChild($tr);

		}

		$table->appendChild($tbody);

		// Create figure element and append the table.
		$figure = $dom->createElement('figure');
		$figure->setAttribute('class', 'wp-block-table');
		$figure->appendChild($table);

		return $figure;
	}

	public function create_cwv_html($nr_metrics)
	{
		$html = file_get_contents(GUTENBERG_TPL . '/cwv.tpl.html');

		$dom = new DOMDocument();
		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

		$metric = $nr_metrics[0] ?? [];

		$this->replaceMetric($dom, $metric, 'CLS', 'cumulativeLayoutShift', 0.1, 0.25, '75', '');

		$this->replaceMetric($dom, $metric, 'INP', 'interactionToNextPaint', 0.2, 0.5, '75', 's');

		$this->replaceMetric($dom, $metric, 'LCP', 'largestContentfulPaint', 2.5, 4, '75', 's');

		return $dom->documentElement;
	}

	private function replaceMetric(&$dom, $metric, $metricName, $metricKey, $goodValue, $badValue, $percentile, $unit)
	{
		$m = $metric['percentile.' . $metricKey] ?? [];

		if (empty($m)) {
			return;
		}

		$value = round($m[$percentile], 2);
		$textColor = 'black';
		$colorClass = 'luminous-vivid-amber';

		if ($value <= $goodValue) {
			$colorClass = 'vivid-green-cyan';
		} else if ($value > $badValue) {
			$colorClass = 'vivid-red';
			$textColor = 'white';
		}

		dom_string_replace($dom, '{{' . strtolower($metricName) . '}}', $value . $unit);
		dom_string_replace($dom, '{{' . strtolower($metricName) . '-text}}', 'has-' . $textColor . '-color has-text-color');
		dom_string_replace($dom, '{{' . strtolower($metricName) . '-text-color}}', $textColor);
		dom_string_replace($dom, '{{' . strtolower($metricName) . '-color}}', $colorClass);
	}

	public function create_p2_headings(&$dom, $heading_type = 'h2',$heading_text = ''){
		$body_el = $dom->getElementsByTagName('body')->item(0);
		$comment = $dom->createComment(" wp:heading ");
		$body_el->appendChild($comment);
		$h2 = $dom->createElement($heading_type, $heading_text);
		$h2->setAttribute('class', 'wp-block-heading');
		$body_el->appendChild($h2);
		$comment = $dom->createComment(" /wp:heading ");
		$body_el->appendChild($comment);
	}

	public function convert_back_to_original_value( $val ){
		// extract unit from $val and $previous_column. case insensitive.
		$val_unit = preg_match('/[a-zA-Z]+/', $val, $matches) ? $matches[0] : '';
		$val_unit = strtolower($val_unit);

		// convert to float.
		$val = str_replace(['s', 'ms', 'm','k', 'b', 't'], '', strtolower($val));
		$val = (float) $val;

		// based on unit, try to convert value to float.
		switch ($val_unit) {
			case 'm':
				$val = (float) $val * 1000000;
				break;
			case 'k':
				$val = (float) $val * 1000;
				break;
			case 'b':
				$val = (float) $val * 1000000000;
				break;
			case 't':
				$val = (float) $val * 1000000000000;
				break;
			default:
				$val = (float) $val;
				break;
		}

		return $val;
	}

}

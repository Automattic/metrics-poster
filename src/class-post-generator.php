<?php

declare(strict_types=1);

namespace MetricPoster;

use GuzzleHttp\Client;
use \DOMDocument;

class PostGenerator
{
	public string $template_file;
	public int $week;
	public int $year;
	public int $clientid;
	public $nr_metrics;
	public bool $show_title;

	public function __construct(string $file_path, int $week, int $year, int $id, $nr_metrics, bool $show_title)
	{
		print "In PostGenerator constructor\n";
		$this->template_file = $file_path;
		$this->week = $week;
		$this->year = $year;
		$this->clientid = $id;
		$this->nr_metrics = $nr_metrics;
		$this->show_title = $show_title;
	}

	public function create_post(): void
	{
		$nr_metrics = $this->nr_metrics->results;

		if (!isset($nr_metrics)) {
			exit('No metrics found');
		}

		// Create the post title with the week and year.
		$date_range = get_week_start_end($this->week, $this->year);
		$fweek_title = sprintf('%s %s-%s, %s', $date_range['week_start_month'], $date_range['week_start_day'], $date_range['week_end_day'], $this->year);
		$title = "Weekly Metrics: $fweek_title";
		
		// Create the main p2 DOMDocument.
		$dom = new DOMDocument();

		if( $this->show_title ) {
			// Load the template file into a DOMDocument.
			$html = file_get_contents($this->template_file);
			$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
			$dom->getElementById("p2-title")->nodeValue = $title;
		}

		// Create the post content with the metrics.
		foreach ($nr_metrics as $metric_key => $metric) {
			switch ($metric_key) {
				case 'cwv':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$cwv_template_html = $this->create_cwv_html($m);
					$importedNode = $dom->importNode($cwv_template_html, true);
					
					// create comment.
					$comment = $dom->createComment(' wp:columns {"verticalAlignment":null,"style":{"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"}}},"fontSize":"large"} ');
					$dom->appendChild($comment);

					// append importedNode to dom.
					$dom->appendChild($importedNode);

					// create closing comment and append to dom.
					$comment = $dom->createComment(' /wp:columns ');
					$dom->appendChild($comment);

					break;
				case '404s':
				case '500s':
				case 'errors':
				case 'warnings':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$table = $this->create_table($dom, $m, "Top {$metric_key}", 'Count');

					// create comment.
					$comment = $dom->createComment(" wp:table ");
					$dom->appendChild($comment);

					// append table to dom.
					$dom->appendChild($table);

					// create closing comment and append to dom.
					$comment = $dom->createComment(" /wp:table ");
					$dom->appendChild($comment);
					break;
				default:
					break;
			}
		}

		// save the DOMDocument as HTML.
		$content_html = $dom->saveHTML();
		
		// If is dev, ignore zapier.
		if (DEV_ENV === 'dev') {
			
			echo $content_html;
			
			// copy the HTML to the clipboard.
			// TODO: make this work better. Currently, only partial HTML is copied.
			shell_exec("echo " . escapeshellarg($content_html) . " | pbcopy");
			exit("\npost copied to clipboard\n");
		}
		
		// Send the post to Zapier.
		$this->zapier_webhook_trigger(getenv('P2_DOMAIN'), $title, $content_html);

		exit("\np2 posted by Zapier!\n");
	}

	public function zapier_webhook_trigger( $site = 'test site', $title = 't title', $body = 't body' ): void {
		// ZAPIER_WEBHOOK_URL is set in the .env file.
		$webhook_url = getenv('ZAPIER_WEBHOOK_URL');
		$webhook_data = array(
			'site' => $site,
			'title' => $title,
			'body' => $body,
		);

		// use GuzzleHttp to send the webhook.
		$client = new Client();
		$response = $client->request('POST', $webhook_url, [
			'json' => $webhook_data,
		]);

		// catch errors.
		if ($response->getStatusCode() !== 200) {
			exit("Error: {$response->getStatusCode()} {$response->getReasonPhrase()} {$response->getBody()}");
		}

	}

	public function create_table($dom, $nr_metrics, $header1 = 'Metric', $header2 = 'Count')
	{
		// create a DOMDocument 2x2 table with a header row and append to $dom.
		$table = $dom->createElement('table');
		$table->setAttribute('class', 'wp-block-table');
		$thead = $dom->createElement('thead');
		$tbody = $dom->createElement('tbody');

		// format counts to be human readable.
		$formatted_counts = array_map(function ($metric) {
			return is_numeric($metric['count']) ? number_format($metric['count']) : 0;
		}, $nr_metrics);

		// append rows to table.
		foreach ($nr_metrics as $key => $metric) {
			$tr = $dom->createElement('tr');

			// sanitize and escape $metric['facet'] to prevent XSS.
			$sanitized_metric = htmlspecialchars($metric['facet']);

			// create table cells and append to row.
			$td = $dom->createElement('td', $sanitized_metric);
			$tr->appendChild($td);
			$td = $dom->createElement('td', $formatted_counts[$key]);
			$tr->appendChild($td);
			$tbody->appendChild($tr);
		}

		// create header rows.
		$tr = $dom->createElement('tr');
		$th = $dom->createElement('th', $header1);
		$tr->appendChild($th);
		$th = $dom->createElement('th', $header2);
		$tr->appendChild($th);

		// append header and body to table.
		$thead->appendChild($tr);
		$table->appendChild($thead);
		$table->appendChild($tbody);

		return $table;
	}

	public function create_cwv_html($nr_metrics) {
		$html = file_get_contents(GUTENBERG_TPL . '/cwv.tpl.html');
	
		$dom = new DOMDocument();
		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
	
		$metric = $nr_metrics[0];
	
		$this->replaceMetric($dom, $metric, 'CLS', 'cumulativeLayoutShift', 0.1, 0.25, '75', 's');
	
		$this->replaceMetric($dom, $metric, 'FID', 'firstInputDelay', 100, 300, '75', 'ms');
	
		$this->replaceMetric($dom, $metric, 'LCP', 'largestContentfulPaint', 2.5, 4, '75', 's');
	
		return $dom->documentElement;
	}
	
	private function replaceMetric( &$dom, $metric, $metricName, $metricKey, $goodValue, $badValue, $percentile, $unit) {
		$m = $metric['percentile.' . $metricKey];
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
	
}

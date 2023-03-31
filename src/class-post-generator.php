<?php

declare(strict_types=1);

namespace MetricPoster;

use \DOMDocument;

class PostGenerator
{
	public string $template_file;
	public int $week;
	public int $year;
	public int $clientid;
	public $nr_metrics;

	public function __construct(string $file_path, int $week, int $year, int $id, $nr_metrics)
	{
		print "In PostGenerator constructor\n";
		$this->template_file = $file_path;
		$this->week = $week;
		$this->year = $year;
		$this->clientid = $id;
		$this->nr_metrics = $nr_metrics;
	}

	public function create(): void
	{
		$nr_metrics = $this->nr_metrics->results;

		if (!isset($nr_metrics)) {
			exit('No metrics found');
		}

		// Load the template file into a DOMDocument.
		$html = file_get_contents($this->template_file);
		$dom = new DOMDocument();
		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

		// Create the post title with the week and year.
		$date_range = get_week_start_end($this->week, $this->year);
		$fweek_title = sprintf('%s %s-%s, %s', $date_range['week_start_month'], $date_range['week_start_day'], $date_range['week_end_day'], $this->year);		
		$dom->getElementById("p2-title")->nodeValue = "Weekly Metrics: $fweek_title";

		// Create the post content with the metrics.
		foreach ($nr_metrics as $metric_key => $metric) {
			switch ($metric_key) {
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
		echo $content_html;

		// copy the HTML to the clipboard.
		// TODO: make this work better. Currently, only partial HTML is copied.
		shell_exec("echo " . escapeshellarg($content_html) . " | pbcopy");

		exit("\npost copied to clipboard\n");
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
}

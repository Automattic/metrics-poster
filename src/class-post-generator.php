<?php

declare(strict_types=1);

namespace MetricPoster;

use \MetricPoster\MetricFetcher;

class PostGenerator
{
	public string $template_file;
	public int $week;
	public int $year;
	public int $clientid;
	public $nr_metrics;
	public $account_id;

	function __construct( $file_path, $week, $year, $id, $nr_metrics )
	{
		print "In PostGenerator constructor\n";
		$this->template_file = $file_path; 
		$this->week = (int) $week; 
		$this->year = (int) $year; 
		$this->clientid = (int) $id;
		$this->account_id = $_ENV['NEW_RELIC_ACCOUNT_ID'];
		$this->nr_metrics = $nr_metrics;
	}

	function create()
	{
		$date_range = get_week_start_end((int) $this->week, $this->year);
		$fweek_title = $date_range['week_start_month'] . " " . $date_range['week_start_day'] . '-' . $date_range['week_end_day'] . ", " . $this->year;
		
		$nr_metrics = $this->nr_metrics;
		
		if( ! isset( $nr_metrics ) ) {
			exit('No metrics found');
		}

		$html = file_get_contents( $this->template_file );
		$dom = new \DOMDocument();
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );

		$dom->getElementById("p2-title")->nodeValue = "Weekly Metrics: $fweek_title";

		$content_html = $dom->createElement('div');

		// create html elements based on $nr_metrics.
		foreach($nr_metrics->results as $metric_key => $metric){
			var_dump($metric_key);
			switch ($metric_key) {
				case '404s':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top 404s', 'Count');
					$content_html->appendChild($html);
					break;
				case '500s':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top 500s', 'Count');
					$content_html->appendChild($html);
					break;
				case 'errors':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top PHP Errors', 'Count');
					$content_html->appendChild($html);
					break;
				case 'warnings':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top PHP Warnings', 'Count');
					$content_html->appendChild($html);
					break;
				default:
					break;
			}
		}

		// find the string {{pattern}} in $dom template and replace with $table element.
		$xpath = new \DOMXPath($dom);
		$nodes = $xpath->query("//*[contains(text(), '{{content}}')]");
		foreach ($nodes as $node) {
			$node->parentNode->replaceChild($content_html, $node);
		}

		// final output
		$p2_html = $dom->saveHTML();
		echo $p2_html;

		// copy to clipboard.
		shell_exec("echo " . escapeshellarg($p2_html) . " | pbcopy");
		exit("\npost copied to clipboard\n");
	}

	public function create_table( &$dom, $nr_metrics, $header1 = 'Metric', $header2 = 'Count' ){
		// create a DOMDocument 2x2 table with a header row and append to $dom.
		$table = $dom->createElement('table');
		$table->setAttribute('class', 'wp-block-table');
		$thead = $dom->createElement('thead');
		$tbody = $dom->createElement('tbody');
						
		// append rows to table.
		foreach ($nr_metrics as $metric) {
			$tr = $dom->createElement('tr');
			$td = $dom->createElement('td', htmlentities($metric['facet']));
			$tr->appendChild($td);
			$td = $dom->createElement('td', (string) $metric['count']);
			$tr->appendChild($td);
			$tbody->appendChild($tr);
		}

		// create header row.
		$tr = $dom->createElement('tr');
		$th = $dom->createElement('th', $header1);
		$tr->appendChild($th);
		$th = $dom->createElement('th', $header2);
		$tr->appendChild($th);


		$thead->appendChild($tr);
		$table->appendChild($thead);
		$table->appendChild($tbody);

		return $table;
	}
}

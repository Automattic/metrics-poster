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

		// create html elements based on $nr_metrics.
		foreach($nr_metrics->results as $metric_key => $metric){
			switch ($metric_key) {
				case '404s':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top 404s', 'Count');
					
					// append comment to $dom.
					$comment = $dom->createComment(' wp:table ');
					$dom->appendChild($comment);
					$dom->appendChild($html);
					$comment = $dom->createComment(' /wp:table ');
					$dom->appendChild($comment);
					break;
				case '500s':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top 500s', 'Count');
					
					// append comment to $dom.
					$comment = $dom->createComment(' wp:table ');
					$dom->appendChild($comment);
					$dom->appendChild($html);
					$comment = $dom->createComment(' /wp:table ');
					$dom->appendChild($comment);
					break;
				case 'errors':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top PHP Errors', 'Count');
					
					// append comment to $dom.
					$comment = $dom->createComment(' wp:table ');
					$dom->appendChild($comment);
					$dom->appendChild($html);
					$comment = $dom->createComment(' /wp:table ');
					$dom->appendChild($comment);
					break;
				case 'warnings':
					$m = $metric['data']['actor']['account']['nrql']['results'];
					$html = $this->create_table($dom, $m, 'Top PHP Warnings', 'Count');
					
					// append comment to $dom.
					$comment = $dom->createComment(' wp:table ');
					$dom->appendChild($comment);
					$dom->appendChild($html);
					$comment = $dom->createComment(' /wp:table ');
					$dom->appendChild($comment);
					break;
				default:
					break;
			}
		}

		// final output
		$content_html = $dom->saveHTML();
		echo $content_html;

		// copy to clipboard.
		shell_exec("echo " . escapeshellarg($content_html) . " | pbcopy");
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

			// sanitize and escape $metric['facet'] to prevent XSS.
			$sanitized_metric = htmlspecialchars($metric['facet'], ENT_QUOTES);

			$td = $dom->createElement('td', $sanitized_metric);
			$tr->appendChild($td);

			// if $metric['count'] is not a number, set it to 0.
			if( ! is_numeric($metric['count']) ) {
				$metric['count'] = 0;
			}

			// format count to be human readable.
			$fcount = number_format($metric['count']);

			$td = $dom->createElement('td', $fcount);
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

<?php

declare(strict_types=1);

namespace MetricPoster;

use GuzzleHttp\Client;

class NewRelicGQL
{
	// newrelic GQL endpoint.
	const NR_GQL_URL = 'https://api.newrelic.com/graphql';

	public string $app_guid;
	public array $date_range;
	public int $clientid;
	public array $metrics;
	public Client $client;
	public $results;
	public $browser_guid;
	public bool $facet_group;
	public string $show_graph_query = '';
	public string $show_table_graph_query = '';

	// constructor with default values.
	public function __construct(int $week, int $year, int $clientid, string $metrics, bool $facet_group = false, bool $show_graph_url = false)
	{
		$date_range = get_week_start_end((int) $week, $year);
		$this->date_range = $date_range;
		$this->clientid = $clientid;
		$this->metrics = explode(',', $metrics);
		$this->browser_guid = $_ENV['NEW_RELIC_BROWSER_GUID'];
		$this->app_guid = $_ENV['NEW_RELIC_APP_GUID'];
		$this->facet_group = $facet_group;
		
		if( $show_graph_url ){
			$this->show_graph_query = ', embeddedChartUrl, staticChartUrl';
			$this->show_table_graph_query = ', embeddedChartUrl(chartType: TABLE), staticChartUrl(chartType: TABLE, format: PNG, height: 480, width: 768)';
		}

		$this->client = new Client([
			'base_uri' => self::NR_GQL_URL,
			'headers' => [
				'X-Api-Key' => $_ENV['NEW_RELIC_API_KEY'],
			],
		]);

		$this->results = $this->get_results();
	}

	// get results from newrelic.
	public function get_results(): array
	{
		$results = [];

		$metric_methods = [
			'apm' => 'get_apm_summary',
			'404s' => 'get_top_404s',
			'500s' => 'get_top_500s',
			'errors' => 'get_php_errors',
			'error_count' => 'get_error_count',
			'warnings' => 'get_php_warnings',
			'warning_count' => 'get_warning_count',
			'cwv' => 'get_browser_web_vitals',
			'transactions' => 'get_top_slow_transactions',
		];

		foreach ($this->metrics as $metric) {
			$metric = strtolower($metric);

			if (array_key_exists($metric, $metric_methods)) {
				$results[$metric] = $this->{$metric_methods[$metric]}();
			}
		}

		return $results;
	}

	// get apm summary from newrelic.
	public function get_apm_summary()
	{

		// nrql query to get apm summary.
		$query = <<<QUERY
		{
			actor {
				entity(guid: "{$this->app_guid}") {
					... on ApmApplicationEntity {
						guid
						name
						apmSummary {
							apdexScore
							errorRate
							responseTimeAverage
							throughput
							webResponseTimeAverage
							webThroughput
							hostCount
						}
					 }
				}
			}
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function get_top_404s()
	{

		$start = $this->date_range["week_start_system"];
		$end = $this->date_range["week_end_system"];
		$limit = 25;
		$facet_query = "";

		// if facet_group is true, then we want to group by uri.
		if ($this->facet_group) {
			$facet_query = "FACET request.uri";
		}

		// nrql query to get top 404s.
		$query = <<<GQL
		{
			actor {
				account(id: {$this->clientid}) {
					nrql(query: "SELECT count(*) FROM Transaction SINCE '$start' UNTIL '$end' WHERE response.statusCode = 404 and request.uri > '' $facet_query LIMIT $limit") {
						results {$this->show_graph_query}
					}
				}
			}
		}
		GQL;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function get_top_500s()
	{
		$facet_query = "";
		
		// if facet_group is true, then we want to group by uri.
		if ($this->facet_group) {
			$facet_query = "FACET request.uri";
		}

		// nrql query to get top 500s.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "SELECT count(*) FROM Transaction SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND response.statusCode = 500 and request.uri > '' $facet_query LIMIT 25") {
					results {$this->show_graph_query}
				  }
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function get_php_errors()
	{
		$facet_query = "";
		
		// if facet_group is true, then we want to group by uri.
		if ($this->facet_group) {
			$facet_query = "FACET errorMessage";
		}

		// nrql query to get php errors.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND errorType LIKE '%error%' AND errorMessage NOT LIKE '%wp-includes/%' AND errorMessage NOT LIKE '%wp-content/db.php%' $facet_query") {
					results {$this->show_table_graph_query}
				  }
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	// Get PHP error counts.
	public function get_error_count(){

		// nrql query to get php errors.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND errorType = 'Error' AND errorMessage NOT LIKE '%wp-includes/%' AND errorMessage NOT LIKE '%wp-content/db.php%'") {
					results {$this->show_table_graph_query}
				  }
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	// Get PHP warning counts.
	public function get_warning_count(){

		// nrql query to get php warnings.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND errorType LIKE '%warning%'") {
					results {$this->show_table_graph_query}
				  }
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}


	public function get_php_cron_errors()
	{
		$facet_query = "";
		
		// if facet_group is true, then we want to group by uri.
		if ($this->facet_group) {
			$facet_query = "FACET errorMessage";
		}

		// nrql query to get php errors.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND errorType = 'NoticedError' AND tags.Environment = 'Production' $facet_query") {
					results {$this->show_table_graph_query}
				  }
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function get_php_warnings()
	{
		$facet_query = "";
		
		// if facet_group is true, then we want to group by uri.
		if ($this->facet_group) {
			$facet_query = "FACET errorMessage";
		}

		// nrql query to get php warnings.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
					nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND errorType LIKE '%warning%' $facet_query") {
						results {$this->show_table_graph_query}
					}
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function get_browser_web_vitals()
	{
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
					nrql(query: "SELECT percentile(largestContentfulPaint, 75), percentile(firstInputDelay, 75), percentile(cumulativeLayoutShift, 75) FROM PageViewTiming WHERE (entityGuid = '{$this->browser_guid}') SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}'") {
						results
					}
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	public function get_top_slow_transactions(){

		$facet_query = "FACET request.uri";

		if ($this->facet_group) {
			$facet_query = "FACET request.uri";
		}

		// Get transaction types.
		$transaction_types = $this->get_top_transaction_types() ?? [];
		$query_builder = [];

		if ( isset($transaction_types['data']['actor']['account']['nrql']['results']) ) {
			$transaction_types = $transaction_types['data']['actor']['account']['nrql']['results'];

			foreach ($transaction_types as $transaction_type) {
				$query_builder[] = "name = '{$transaction_type['name']}'";
			}

			// implode if we have more than one.
			if (count($query_builder) > 1) {
				$query_builder = implode(" OR ", $query_builder);
			} else {
				$query_builder = $query_builder[0];
			}

		} else {
			$query_builder = "";
		}

		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
					nrql(query: "FROM Transaction SELECT average(duration) WHERE entityGuid = '{$this->app_guid}' AND ($query_builder) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' $facet_query") {
						results
					}
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

	// return type array.
	public function get_top_transaction_types(): array {

		$facet_query = "";

		if ($this->facet_group) {
			$facet_query = "FACET name";
		}

		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
					nrql(query: "FROM Transaction SELECT sum(totalTime) WHERE entityGuid = '{$this->app_guid}' AND name NOT LIKE 'WebTransaction/StatusCode/%' SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' $facet_query LIMIT 5") {
						results
					}
				}
			  }
		}
		QUERY;

		$response = $this->client->request('POST', '', [
			'json' => [
				'query' => $query,
			],
		]);

		$body = $response->getBody()->getContents();
		return json_decode($body, true);
	}

}

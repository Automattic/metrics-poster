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
	public string $clientid;
	public string $account_id;
	public string $metrics;
	public Client $client;

	// constructor with default values.
	public function __construct($week = '', $year = '', $clientid = '', $metrics = '')
	{
		$date_range = get_week_start_end((int) $week, $year);

		$this->app_guid = $_ENV['NEW_RELIC_APP_GUID'];
		$this->account_id = $_ENV['NEW_RELIC_ACCOUNT_ID'];

		$this->date_range = $date_range;
		$this->clientid = $clientid;
		$this->metrics = $metrics;


		$this->client = new Client([
			'base_uri' => self::NR_GQL_URL,
			'headers' => [
				'X-Api-Key' => $_ENV['NEW_RELIC_API_KEY'],
			],
		]);
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

		// nrql query to get top 404s.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->account_id) {
				  nrql(query: "SELECT count(*) FROM Transaction SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE response.statusCode = 404 and request.uri > '' FACET request.uri LIMIT 25") {
					results,
					embeddedChartUrl,
					staticChartUrl
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

	public function get_top_500s()
	{

		// nrql query to get top 500s.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->account_id) {
				  nrql(query: "SELECT count(*) FROM Transaction SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE response.statusCode = 500 and request.uri > '' FACET request.uri LIMIT 25") {
					results,
					embeddedChartUrl,
					staticChartUrl
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

	public function get_php_errors(){
		// nrql query to get php errors.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->account_id) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE errorType LIKE '%error%' AND tags.Environment = 'Production' FACET errorMessage") {
					results,
					embeddedChartUrl(chartType: TABLE)
					staticChartUrl(chartType: TABLE, format: PNG, height: 480, width: 768)
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

	public function get_php_warnings(){
		// nrql query to get php warnings.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->account_id) {
					nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE errorType LIKE '%warning%' AND tags.Environment = 'Production' FACET errorMessage") {
						results,
						embeddedChartUrl(chartType: TABLE)
						staticChartUrl(chartType: TABLE, format: PNG, height: 480, width: 768)
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

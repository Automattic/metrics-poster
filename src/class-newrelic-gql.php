<?php

declare(strict_types=1);

namespace MetricPoster;

use GuzzleHttp\Client;
use InvalidArgumentException;
use MetricPoster\Jetpack_Metrics;

class NewRelicGQL
{
	private const NR_GQL_URL = 'https://api.newrelic.com/graphql';

	private string $app_guid;
	private string $browser_guid;
	private string $appname;
	private string $jp_blogid;
	private array $date_range;
	private int $clientid;
	private string $appid;
	private int $week;
	private array $metrics;
	private Client $client;
	private bool $facet_group;
	private string $show_graph_query = '';
	private string $show_table_graph_query = '';
	public string $error_where_clause = "errorType LIKE '%error%' AND errorMessage NOT LIKE '%wp-includes/%' AND errorMessage NOT LIKE '%wp-content/db.php%' AND errorMessage NOT LIKE '%Call to undefined function%'";
	public string $warning_where_clause = "errorType LIKE '%warning%'";
	private string $nrkey;

	public function __construct($app_info, int $week, int $year, int $clientid, string $metrics, bool $facet_group = false, bool $show_graph_url = false)
	{
		$this->date_range = get_week_start_end((int)$week, $year);
		$this->clientid = $clientid;
		$this->week = $week;
		$this->metrics = explode(',', $metrics);
		$this->browser_guid = $app_info->get_nr_browser_guid();
		$this->app_guid = $app_info->get_nr_app_guid();
		$this->appid = $app_info->get_app_id();
		$this->appname = $app_info->get_app_name();
		$this->jp_blogid = $app_info->get_jp_blogid();
		$this->facet_group = $facet_group;

		if ($show_graph_url) {
			$this->show_graph_query = ', embeddedChartUrl, staticChartUrl';
			$this->show_table_graph_query = ', embeddedChartUrl(chartType: TABLE), staticChartUrl(chartType: TABLE, format: PNG, height: 480, width: 768)';
		}

		
		if ( \vip_get_env_var( 'NEW_RELIC_API_KEY', '' ) || $_ENV['NEW_RELIC_API_KEY'] ) {
			$this->nrkey = \vip_get_env_var( 'NEW_RELIC_API_KEY', $_ENV['NEW_RELIC_API_KEY'] ?? '' );
		} else {
			throw new InvalidArgumentException( 'New Relic API key not defined' );
		} 

		// check if key is set
		if (empty($this->nrkey)) {
			throw new InvalidArgumentException('New Relic API key not defined');
		}

		$this->client = new Client([
			'base_uri' => self::NR_GQL_URL,
			'headers' => [
				'X-Api-Key' => $this->nrkey,
			],
		]);
	}

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
			'cwv_chart' => 'get_cwv_by_pageview_chart',
			'jetpack_pageviews' => 'get_jetpack_pageviews',
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

	private function nrqlQuery(string $query): array
	{
		// Send the API request and handle response
		try {
			$response = $this->client->request('POST', '', [
				'json' => [
					'query' => $query,
				],
			]);

			$body = $response->getBody()->getContents();
			return json_decode($body, true);
		} catch (\Exception $e) {
			// Handle the exception (e.g., log the error)
			// For now, just return an empty array
			echo $e->getMessage();
			return [];
		}
	}

	public function get_apm_summary(): array
	{
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

		return $this->nrqlQuery($query);
	}

	public function get_top_404s(): array
	{
		$start = $this->date_range["week_start_system"];
		$end = $this->date_range["week_end_system"];
		$limit = 25;
		$facet_query = $this->facet_group ? "FACET request.uri" : "";

		$query = <<<GQL
        {
            actor {
                account(id: {$this->clientid}) {
                    nrql(query: "SELECT count(*) FROM Transaction SINCE '$start' UNTIL '$end' WHERE entityGuid = '{$this->app_guid}' AND response.statusCode = 404 and request.uri > '' $facet_query LIMIT $limit") {
                        results {$this->show_graph_query}
                    }
                }
            }
        }
        GQL;

		return $this->nrqlQuery($query);
	}

	public function get_top_500s(): array
	{
		$facet_query = $this->facet_group ? "FACET request.uri" : "";

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

		return $this->nrqlQuery($query);
	}

	public function get_php_errors(): array
	{
		// Validate input parameters
		if (empty($this->clientid) || empty($this->app_guid)) {
			throw new InvalidArgumentException("clientid and app_guid must be provided.");
		}

		$facet_query = $this->facet_group ? "FACET errorMessage" : "";

		$query = <<<QUERY
        {
            actor {
                account(id: {$this->clientid}) {
                    nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND {$this->error_where_clause} {$facet_query}") {
                        results {$this->show_table_graph_query}
                    }
                }
            }
        }
        QUERY;

		return $this->nrqlQuery($query);
	}

	// Get PHP error counts.
	public function get_error_count()
	{

		// nrql query to get php errors.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND {$this->error_where_clause}") {
					results {$this->show_table_graph_query}
				  }
				}
			  }
		}
		QUERY;

		// return $this->nrqlQuery($query);
		$mpost_id = $this->update_metric_posts('error_count', $query);
		// return post object with post meta error_count.
		return \get_post($mpost_id);
	}

	// Get PHP warning counts.
	public function get_warning_count()
	{

		// nrql query to get php warnings.
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
				  nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND {$this->warning_where_clause}") {
					results {$this->show_table_graph_query}
				  }
				}
			  }
		}
		QUERY;

		// return $this->nrqlQuery($query);
		$mpost_id = $this->update_metric_posts('warning_count', $query);
		// return post object with post meta warning_count.
		return \get_post($mpost_id);
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

		return $this->nrqlQuery($query);
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
					nrql(query: "FROM Transaction SELECT count(*) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' WHERE entityGuid = '{$this->app_guid}' AND {$this->warning_where_clause} $facet_query") {
						results {$this->show_table_graph_query}
					}
				}
			  }
		}
		QUERY;

		return $this->nrqlQuery($query);
	}

	public function get_cwv_by_pageview_chart()
	{
		$query = <<<QUERY
		{
			actor {
				account(id: $this->clientid) {
					nrql(query: "FROM PageViewTiming JOIN (FROM PageView SELECT count(*) as pvcount WHERE (entityGuid = '{$this->browser_guid}') SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' FACET pageUrl) ON pageUrl SELECT latest(pvcount) as 'Page Views', percentile(largestContentfulPaint, 75) as 'LCP', percentile(firstInputDelay, 75) AS 'FID', percentile(cumulativeLayoutShift, 75) as 'CLS' WHERE (entityGuid = '{$this->browser_guid}') SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' FACET pageUrl") {
						results {$this->show_table_graph_query}
					}
				}
			  }
		}
		QUERY;

		return $this->nrqlQuery($query);
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

		return $this->nrqlQuery($query);
	}

	public function get_top_slow_transactions()
	{
		$facet_query = $this->facet_group ? "FACET request.uri" : "";

		$transactionTypesQuery = $this->buildTransactionTypesQuery();

		$query = <<<QUERY
    {
        actor {
            account(id: $this->clientid) {
                nrql(query: "FROM Transaction SELECT average(duration) WHERE entityGuid = '{$this->app_guid}' AND ($transactionTypesQuery) SINCE '{$this->date_range["week_start_system"]}' UNTIL '{$this->date_range["week_end_system"]}' $facet_query") {
                    results
                }
            }
        }
    }
    QUERY;

		return $this->nrqlQuery($query);
	}

	private function buildTransactionTypesQuery(): string
	{
		$transactionTypesData = $this->get_top_transaction_types();

		if (!isset($transactionTypesData['data']['actor']['account']['nrql']['results'])) {
			return "";
		}

		$transactionTypes = $transactionTypesData['data']['actor']['account']['nrql']['results'];
		$queryBuilder = [];

		foreach ($transactionTypes as $transactionType) {
			$queryBuilder[] = "name = '{$transactionType['name']}'";
		}

		return implode(" OR ", $queryBuilder);
	}

	// return type array.
	public function get_top_transaction_types(): array
	{

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

		return $this->nrqlQuery($query);
	}

	// TODO: refactor and combine with get_jetpack_pageviews.
	// function to fetch and update cpt metric_posts
	public function update_metric_posts( $metaname = 'error_count', $query )
	{
		// fetch cpt metric_posts by postmeta appid.
		$args = array( 
			'post_type' => 'metric_posts',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'appid',
					'value' => $this->appid,
					'compare' => '=',
				),
			),
		);

		$posts = \get_posts( $args );

		// if no posts, create one.
		if ( empty( $posts ) ) {
			$post_id = \wp_insert_post( array(
				'post_title' => $this->appname,
				'post_type' => 'metric_posts',
				'post_status' => 'publish',
			) );
			
			// run query.
			$query_results = $this->nrqlQuery( $query );
			$query_results = $query_results['data']['actor']['account']['nrql']['results'][0]['count'] ?? [];

			// add post meta.
			\add_post_meta( $post_id, 'appid', $this->appid );
			\add_post_meta( $post_id, $metaname, serialize([ "{$this->week}" => $query_results ]) );

		} else {

			// get count post meta.
			$count = \get_post_meta( $posts[0]->ID, $metaname, true );

			if ( empty( $count ) || ! is_string( $count ) ) {
				$count = [];
			} else {
				// unserialize count.
				$count = unserialize( $count );				
			}

			// check if week is already added.
			if ( isset( $count[ "{$this->week}" ] ) ) {
				return \get_post($posts[0]->ID);
			}

			// run query.
			$query_results = $this->nrqlQuery( $query );
			$query_results = $query_results['data']['actor']['account']['nrql']['results'][0]['count'] ?? [];

			// add new week to count.
			$count[ "{$this->week}" ] = $query_results;

			// sort count by week.
			ksort( $count );

			// trim array to last 6 weeks.
			if ( count( $count ) > 6 ) {
				$count = array_slice( $count, -6, 6, true );
			}

			// serialize count.
			$count = serialize( $count );

			// update post meta.
			\update_post_meta( $posts[0]->ID, $metaname, $count );

			$post_id = $posts[0]->ID;
		}

		// return post id.
		return $post_id;

	}

	public function get_jetpack_pageviews(){
		// Jetpack_Metrics class instance
		$jp = new Jetpack_Metrics($this->jp_blogid);

		// fetch cpt metric_posts by postmeta appid.
		$args = array( 
			'post_type' => 'metric_posts',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => 'appid',
					'value' => $this->appid,
					'compare' => '=',
				),
			),
		);

		$posts = \get_posts( $args );

		// if no posts, create one.
		if ( empty( $posts ) ) {
			$post_id = \wp_insert_post( array(
				'post_title' => $this->appname,
				'post_type' => 'metric_posts',
				'post_status' => 'publish',
			) );
			
			// get stats from jetpack
			$query_results = $jp->get_stats(7, $this->date_range["jp_week_end"]);

			// add post meta.
			\add_post_meta( $post_id, 'appid', $this->appid );
			\add_post_meta( $post_id, 'jetpack_pageviews', serialize([ "{$this->week}" => $query_results ]) );

		} else {

			// get count post meta.
			$count = \get_post_meta( $posts[0]->ID, 'jetpack_pageviews', true );

			if ( empty( $count ) || ! is_string( $count ) ) {
				$count = [];
			} else {
				// unserialize count.
				$count = unserialize( $count );				
			}

			// check if week is already added.
			if ( isset( $count[ "{$this->week}" ] ) ) {
				return \get_post($posts[0]->ID);
			}

			// run query.
			$query_results = $jp->get_stats(7, $this->date_range["jp_week_end"]);

			// add new week to count.
			$count[ "{$this->week}" ] = $query_results;

			// sort count by week.
			ksort( $count );

			// trim array to last 6 weeks.
			if ( count( $count ) > 6 ) {
				$count = array_slice( $count, -6, 6, true );
			}

			// serialize count.
			$count = serialize( $count );

			// update post meta.
			\update_post_meta( $posts[0]->ID, 'jetpack_pageviews', $count );

			$post_id = $posts[0]->ID;
		}

		// return stats
		return \get_post($post_id);

	}
}

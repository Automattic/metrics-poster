<?php

declare(strict_types=1);

namespace MetricPoster;

use function MetricPoster\Utils\zapier_webhook_trigger;
use MetricPoster\JsonToGutenbergTable;
use DOMDocument;

class PostGenerator {

	public string $template_file;
	public int $week;
	public int $year;
	public int $clientid;
	public $nr_metrics;
	public bool $show_header_footer;
	public string $app_name;

	public function __construct( string $file_path, int $week, int $year, $nr_metrics, bool $show_header_footer, string $app_name = 'test app' ) {
		$this->template_file = $file_path;
		$this->week          = $week;
		$this->year          = $year;
		$this->nr_metrics    = $nr_metrics;

		// TODO: Should be more descriptive as it uses template files and not just headings.
		$this->show_header_footer = $show_header_footer;
		$this->app_name           = $app_name;
	}

	public function create_post() {
		$nr_metrics = $this->nr_metrics;

		if ( ! isset( $nr_metrics ) ) {
			exit( 'No metrics found' );
		}

		// Create the post title with the week and year.
		$date_range  = get_week_start_end( $this->week, $this->year );
		$fweek_title = sprintf( '%s %s-%s, %s', $date_range['week_start_month'], $date_range['week_start_day'], $date_range['week_end_day'], $this->year );

		// Create the main p2 DOMDocument.
		$dom = new DOMDocument();

		$content_dom = new DOMDocument();
		$content_dom->loadHTML( '<body></body>' );
		$content_body = $content_dom->getElementsByTagName( 'body' )->item( 0 );

		if ( $this->show_header_footer ) {
			// Load the template file into a DOMDocument.
			$html = file_get_contents( $this->template_file );
			$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
		
			dom_string_replace( $dom, '{{week}}', $this->week );
			dom_string_replace( $dom, '{{date_range}}', $fweek_title );
			dom_string_replace( $dom, '{{app_name}}', $this->app_name );
			dom_string_replace( $dom, '{{current_year}}', $this->year );
		} else {
			// file_get_contents($this->template_file);
			$html = file_get_contents( GUTENBERG_TPL . '/post-no-header-footers.html' );
			$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
		}

		// Create the post content with the metrics.
		foreach ( $nr_metrics as $metric_key => $metric ) {
			switch ( $metric_key ) {
				case 'cwv':
					$this->create_p2_headings( $content_dom, 'h2', 'Core Web Vitals (CWV)' );

					// convert json to array.
					$metric = json_decode( $metric, true );

					$m                 = $metric['data']['actor']['account']['nrql']['results'] ?? array();
					$cwv_template_html = $this->create_cwv_html( $m );
					$importedNode      = $content_dom->importNode( $cwv_template_html, true );
					
					// create comment.
					$comment = $content_dom->createComment( ' wp:columns {"verticalAlignment":null,"style":{"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"}}},"fontSize":"large"} ' );
					
					$content_body->appendChild( $comment );

					// append importedNode to dom.
					$content_body->appendChild( $importedNode );

					// create closing comment and append to dom.
					$comment = $content_dom->createComment( ' /wp:columns ' );
					$content_body->appendChild( $comment );

					break;
				case 'cwv_extended':
				case 'cwv_mobile_extended':
					$metric_names = array(
						'cwv_extended'        => 'Core Web Vitals Week Over Week',
						'cwv_mobile_extended' => 'Core Web Vitals (Mobile) Week Over Week',
					);

					$caption_text = 'The period of weekly metrics collection is Sunday to Saturday';
					$heading_text = "{$metric_names[$metric_key]}";


					// get postmeta with meta_key $metric_names[$metric_key]
					$metric_meta_value = \get_post_meta( $metric->ID, $metric_key, true );
					
					if ( ! $metric_meta_value ) {
						// wp error log
						error_log( "No postmeta found for {$metric_key} in post {$metric->ID}" );
						
						continue;
					}

					// unserialize $metric_meta_value.
					$weekly_cwv_metrics = unserialize( $metric_meta_value ) ?? array();

					// if $weekly_cwv_metrics is empty, continue.
					if ( empty( $weekly_cwv_metrics ) ) {
						continue;
					}

					// create pivot_columns.
					$week_array = array_keys( $weekly_cwv_metrics );

					// create pivot_columns.
					$week_headers = array_map(
						function ( $key ) {
							return "Week {$key}";
						},
						$week_array
					);

					array_unshift( $week_headers, '' ); // first header column is empty.

					// create cwv rows.
					$rows = array(
						'cumulativeLayoutShift'  => array(
							'metric' => 'Cumulative Layout Shift (CLS)',
							'slug'   => 'cumulativeLayoutShift',
							'data'   => array(),
						),
						'firstContentfulPaint'   => array(
							'metric' => 'First Contentful Paint (FCP)',
							'slug'   => 'firstContentfulPaint',
							'data'   => array(),
						),
						'firstPaint'             => array(
							'metric' => 'First Paint',
							'slug'   => 'firstPaint',
							'data'   => array(),
						),
						'interactionToNextPaint' => array(
							'metric' => 'Interaction to Next Paint (INP)',
							'slug'   => 'interactionToNextPaint',
							'data'   => array(),
						),
						'largestContentfulPaint' => array(
							'metric' => 'Largest Contentful Paint (LCP)',
							'slug'   => 'largestContentfulPaint',
							'data'   => array(),
						),
					);
					
					foreach ( $weekly_cwv_metrics as $week_m ) {

						// check if $week_m is an array.
						// TODO: This is a hack to handle the fact that the data is sometimes nested in an array.
						if ( is_array( $week_m ) and isset( $week_m[0] ) ) {
							$week_m = $week_m[0];
						}

						foreach ( $week_m as $key => $value ) {
							$metric = str_replace( 'percentile.', '', $key );
							$value  = $value['75'] ?? 0;
							$value  = number_format( $value, 2 );
							$unit   = get_cwv_metric( $metric );
					
							if ( isset( $rows[ $metric ] ) ) {
								$rows[ $metric ]['data'][] = array(
									'value' => "{$value} {$unit}",
									'slug'  => $rows[ $metric ]['slug'],
								);
							}
						}
					}
					
					// Extracting rows into variables if needed
					$cls_row = $rows['cumulativeLayoutShift']['data'];
					array_unshift( $cls_row, $rows['cumulativeLayoutShift']['metric'] );
					$fcp_row = $rows['firstContentfulPaint']['data'];
					array_unshift( $fcp_row, $rows['firstContentfulPaint']['metric'] );
					$fp_row = $rows['firstPaint']['data'];
					array_unshift( $fp_row, $rows['firstPaint']['metric'] );
					$inp_row = $rows['interactionToNextPaint']['data'];
					array_unshift( $inp_row, $rows['interactionToNextPaint']['metric'] );
					$lcp_row = $rows['largestContentfulPaint']['data'];
					array_unshift( $lcp_row, $rows['largestContentfulPaint']['metric'] );

					// Full metrics array.
					$metric_rows = array( $cls_row, $fcp_row, $fp_row, $inp_row, $lcp_row );

					// heading
					$this->create_p2_headings( $content_dom, 'h2', $heading_text );

					// create create_html_table.
					$table = new JsonToGutenbergTable(
						$metric_rows, 
						$week_headers, 
						'table', 
						array( $this, 'metric_value_color' ),
						$caption_text
					);

					$table = $table->getTableDomDocument();

					// for each table childNodes, append to $content_body.
					foreach ( $table->childNodes as $child ) {
						$importedNode = $content_dom->importNode( $child, true );
						$content_body->appendChild( $importedNode );
					}
					
					break;
				case '404s':
				case '500s':
				case 'errors':
				case 'warnings':
				case 'top_user_agents':
					// convert json to array.
					$m = json_decode( $metric, true );
					$m = $m['data']['actor']['account']['nrql']['results'] ?? array();

					// skip if $m is empty or not an array.
					if ( empty( $m ) || ! is_array( $m ) ) {
						continue;
					}

					// map filter to $m.
					$m = array_map(
						function ( $item ) {
							// escape and sanitize
							$metric = strip_tags( $item['facet'] );
							$metric = stripslashes( $metric );

							return array(
								'facet' => $metric,
								'count' => number_format( $item['count'] ),
							);
						},
						$m
					);
					
					$metric_names = array(
						'404s'            => '404s',
						'500s'            => '500s',
						'errors'          => 'Errors',
						'warnings'        => 'Warnings',
						'top_user_agents' => 'Top User Agents',
					);
					
					$heading_text = "Top {$metric_names[$metric_key]}";
					$this->create_p2_headings( $content_dom, 'h2', $heading_text );

					$caption_text = "Counts and listing of most frequently occurring {$metric_names[$metric_key]}";
					$table        = new JsonToGutenbergTable( $m, array( $heading_text, 'Count' ), 'table', array( $this, 'metric_value_color' ), $caption_text );
					
					$table = $table->getTableDomDocument();

					// for each table childNodes, append to $content_body.
					foreach ( $table->childNodes as $child ) {
						$importedNode = $content_dom->importNode( $child, true );
						$content_body->appendChild( $importedNode );
					}

					break;
				case 'error_count':
				case 'warning_count':
				case 'jetpack_pageviews':
				case 'slow_queries':
				case 'response_time':
				case 'throughput':
				case 'apdex':
					$metric_names = array(
						'error_count'       => 'PHP Errors',
						'warning_count'     => 'PHP Warnings',
						'jetpack_pageviews' => 'Jetpack Page Views',
						'slow_queries'      => 'Slow Queries',
						'response_time'     => 'Average Response Time p75',
						'throughput'        => 'Throughput',
						'apdex'             => 'Apdex',
					);

					$caption_text = 'The period of weekly metrics collection is Sunday to Saturday';
					$heading_text = "{$metric_names[$metric_key]}";

					// get postmeta with meta_key $metric_names[$metric_key]
					$metric_meta_value = \get_post_meta( $metric->ID, $metric_key, true );
					
					// unserialize $metric_meta_value.
					$metrics_array = unserialize( $metric_meta_value ) ?? array();

					// if $metrics_array is empty, continue.
					if ( empty( $metrics_array ) ) {
						continue;
					}

					// get keys from $metrics_array for week numbers.
					$week_array = array_keys( $metrics_array );

					// create pivot_columns.
					$weeks_headers = array_map(
						function ( $key ) {
							return "Week {$key}";
						},
						$week_array
					);

					// create $metric_names[$metric_key] row.
					$metrics_array = array_map(
						function ( $val ) {

							// if is number and not float.
							if ( is_numeric( $val ) && ! is_float( $val ) ) {
								// thousand separator.
								$val = number_format( $val );
							}

							// if is float, round to 3 decimal places.
							if ( is_float( $val ) ) {
								$val = number_format( $val, 3 );
							}

							return $val;
						},
						$metrics_array 
					);
					
					// create Change row.
					$change_row_array = array_map(
						function ( $week ) use ( $metrics_array ) {

							$change = 0;

							$previous_column = getPrevKey( $week, $metrics_array );
							$previous_column = $metrics_array[ $previous_column ] ?? null;

 							$current_column = $metrics_array[ $week ] ?? 0;

							// check if previous $column exists.
							if ( $previous_column !== null ) {
								// try to remove units from $current_column and $previous_column.
								$current_column  = convert_back_to_original_value( $current_column );
								$previous_column = convert_back_to_original_value( $previous_column );
		
								if ( is_numeric( $previous_column ) && 0 != $previous_column ) {
									$change = round( ( ( $current_column - $previous_column ) / $previous_column ) * 100, 2 );
								} elseif ( is_numeric( $previous_column ) && 0 == $previous_column && 0 < $current_column ) {
									$change = round( $current_column * 100, 2 );
								} else {
									$change = 0;
								}
							}

							return "{$change}%";
						},
						$week_array
					);

					// add label cell to beginning of each row.
					array_unshift( $metrics_array, $heading_text );
					array_unshift( $change_row_array, 'Change' );
					array_unshift( $weeks_headers, '' ); // first column is empty.

					$metric_rows = array( $metrics_array, $change_row_array );

					// create heading.
					$this->create_p2_headings( $content_dom, 'h2', $heading_text );

					// create create_html_table.
					$table = new JsonToGutenbergTable( $metric_rows, $weeks_headers, 'table', array( $this, 'metric_value_color' ), $caption_text );
					$table = $table->getTableDomDocument();

					// for each table childNodes, append to $content_body.
					foreach ( $table->childNodes as $child ) {
						$importedNode = $content_dom->importNode( $child, true );
						$content_body->appendChild( $importedNode );
					}

					break;
				case 'transactions':
				case 'slow_api_transactions':
					// convert json to array.
					$m = json_decode( $metric, true );
					$m = $m['data']['actor']['account']['nrql']['results'] ?? array();

					// skip if $m is empty or not an array.
					if ( empty( $m ) || ! is_array( $m ) ) {
						continue;
					}

					$header_label = 'Top Slow Transactions';

					if ( 'slow_api_transactions' === $metric_key ) {
						$header_label = 'Top Slow Endpoint Transactions';
					}

					// map filter to $m.
					$m = array_map(
						function ( $item ) {
							$formatted_value = $item['average.duration'] ?? 0;
							$formatted_value = number_format( $formatted_value, 2 );

							return array(
								'facet'            => $item['facet'],
								'average.duration' => $formatted_value,
							);
						},
						$m
					);

					$this->create_p2_headings( $content_dom, 'h2', $header_label );
					
					$caption_text = 'Average duration of slow transactions';

					$table = new JsonToGutenbergTable( $m, array( "Top {$metric_key}", 'Average Duration (ms)' ), 'table', array( $this, 'metric_value_color' ), $caption_text );
					$table = $table->getTableDomDocument();

					// for each table childNodes, append to $content_body.
					foreach ( $table->childNodes as $child ) {
						$importedNode = $content_dom->importNode( $child, true );
						$content_body->appendChild( $importedNode );
					}

					break;
				case 'cwv_chart':
					// convert json to array.
					$m = json_decode( $metric, true );
					$m = $m['data']['actor']['account']['nrql']['results'] ?? array();

					// skip if $m is empty or not an array.
					if ( empty( $m ) || ! is_array( $m ) ) {
						continue;
					}

					// create rows.
					$m = array_map(
						function ( $item ) {
							$cls = $item['CLS']['75'] ?? 0;
							$cls = number_format( $cls, 2 );

							$inp = $item['INP']['75'] ?? 0;
							$inp = number_format( $inp, 2 );

							$lcp = $item['LCP']['75'] ?? 0;
							$lcp = number_format( $lcp, 2 );

							$page_views = $item['pageViews'] ?? 0;
							$page_views = number_format( $page_views );

							return array(
								'Page URL'  => $item['pageUrl'],
								'CLS'       => $cls,
								'INP'       => $inp,
								'LCP'       => $lcp,
								'pageViews' => $page_views,
							);
						},
						$m
					);

					// create heading.
					$this->create_p2_headings( $content_dom, 'h2', 'Core Web Vitals (CWV)' );

					// create caption.
					$caption_text = 'Core Web Vitals (CWV) for top pages';

					// create table.
					$table = new JsonToGutenbergTable( $m, array( 'Page URL', 'CLS', 'INP', 'LCP', 'Page Views' ), 'table', array( $this, 'metric_value_color' ), $caption_text );
					
					$table = $table->getTableDomDocument();

					// for each table childNodes, append to $content_body.
					foreach ( $table->childNodes as $child ) {
						$importedNode = $content_dom->importNode( $child, true );
						$content_body->appendChild( $importedNode );
					}

					break;
				default:
					var_dump( $metric );
					break;
			}
		}

		// get body of $content_dom.
		$content_dom  = $content_dom->getElementsByTagName( 'body' )->item( 0 );
		$content_body = $dom->importNode( $content_dom, true );

		// find and replace string {{content_body}} in &$dom with $content_html.
		$this->replace_content_body( $dom, $content_body );
		
		// Save the DOMDocument as HTML.
		$final_html_markup = $dom->saveHTML();
		
		// Required for smooth content body import.
		$final_html_markup = str_replace( array( '<body>', '</body>' ), '', $final_html_markup );
		$final_html_markup = str_replace( '<p><!--', '<!--', $final_html_markup );
		$final_html_markup = str_replace( '--></p>', '-->', $final_html_markup );
		$final_html_markup = preg_replace( '/<p[^>]*>([\s]|&nbsp;)*<\/p>/', '', $final_html_markup );

		// if ZAPIER env is set, send the post to Zapier.
		if ( isset( $_ENV['ZAPIER'] ) ) {
			$title = "Week {$this->week} Metrics for {$this->app_name}: $fweek_title";
			zapier_webhook_trigger( $_ENV['P2_DOMAIN'], $title, $final_html_markup );
			exit( "\np2 posted by Zapier!\n" );
		}

		return $final_html_markup;
	}

	public function replace_content_body( &$dom, $content_html ) {
		$xpath = new \DOMXPath( $dom );
		
		// Find string NODE {{content_body}} in $dom.
		$nodes = $xpath->query( '//text()[contains(., "{{content_body}}")]' );

		foreach ( $nodes as $node ) {
			$node->parentNode->replaceChild( $content_html, $node );
		}
	}

	public function create_cwv_html( $nr_metrics ) {
		$html = file_get_contents( GUTENBERG_TPL . '/cwv.tpl.html' );

		$dom = new DOMDocument();
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );

		$metric = $nr_metrics[0] ?? array();

		$this->replaceMetric( $dom, $metric, 'CLS', 'cumulativeLayoutShift', 0.1, 0.25, '75', '' );

		$this->replaceMetric( $dom, $metric, 'INP', 'interactionToNextPaint', 0.2, 0.5, '75', 's' );

		$this->replaceMetric( $dom, $metric, 'LCP', 'largestContentfulPaint', 2.5, 4, '75', 's' );

		return $dom->documentElement;
	}

	private function replaceMetric( &$dom, $metric, $metricName, $metricKey, $goodValue, $badValue, $percentile, $unit ) {
		$m = $metric[ 'percentile.' . $metricKey ] ?? array();

		if ( empty( $m ) ) {
			return;
		}

		$value      = round( $m[ $percentile ], 2 );
		$textColor  = 'black';
		$colorClass = 'luminous-vivid-amber';

		if ( $value <= $goodValue ) {
			$colorClass = 'vivid-green-cyan';
		} elseif ( $value > $badValue ) {
			$colorClass = 'vivid-red';
			$textColor  = 'white';
		}

		dom_string_replace( $dom, '{{' . strtolower( $metricName ) . '}}', $value . $unit );
		dom_string_replace( $dom, '{{' . strtolower( $metricName ) . '-text}}', 'has-' . $textColor . '-color has-text-color' );
		dom_string_replace( $dom, '{{' . strtolower( $metricName ) . '-text-color}}', $textColor );
		dom_string_replace( $dom, '{{' . strtolower( $metricName ) . '-color}}', $colorClass );
	}

	public function create_p2_headings( &$dom, $heading_type = 'h2', $heading_text = '' ) {
		$body_el = $dom->getElementsByTagName( 'body' )->item( 0 );
		$comment = $dom->createComment( ' wp:heading ' );
		$body_el->appendChild( $comment );
		$h2 = $dom->createElement( $heading_type, $heading_text );
		$h2->setAttribute( 'class', 'wp-block-heading' );
		$body_el->appendChild( $h2 );
		$comment = $dom->createComment( ' /wp:heading ' );
		$body_el->appendChild( $comment );
	}

	public function metric_value_color( $value, $metric_name = '' ) {

		// null check $value
		if ( is_null( $value ) ) {
			return '';
		}

		// colors
		$vivid_green_cyan     = '#00D084';
		$luminous_vivid_amber = '#FFC400';
		$vivid_red            = '#CF2E2E';
	
		// text color
		$black = 'black';
		$white = 'white';
	
		// default color.
		$text_color = $black;

		// strip unit and all spaces from $value.
		if ( is_string( $value ) ) {
			$value = preg_replace( '/\s+/', '', $value );
			$value = preg_replace( '/[a-zA-Z]+/', '', $value );
		}

		$value = (float) $value;
	
		switch ( $metric_name ) {
			case 'CLS':
			case 'cumulativeLayoutShift':
				if ( $value <= 0.1 ) {
					$color_hex = $vivid_green_cyan;
				} elseif ( $value > 0.25 ) {
					$color_hex  = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'FCP':
			case 'firstContentfulPaint':
				if ( $value <= 2.5 ) {
					$color_hex = $vivid_green_cyan;
				} elseif ( $value > 4 ) {
					$color_hex  = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'FID':
			case 'firstInputDelay':
				if ( $value <= 0.2 ) {
					$color_hex = $vivid_green_cyan;
				} elseif ( $value > 0.5 ) {
					$color_hex  = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'LCP':
			case 'largestContentfulPaint':
				if ( $value <= 2.5 ) {
					$color_hex = $vivid_green_cyan;
				} elseif ( $value > 4 ) {
					$color_hex  = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'FP':
			case 'firstPaint':
				if ( $value <= 2.5 ) {
					$color_hex = $vivid_green_cyan;
				} elseif ( $value > 4 ) {
					$color_hex  = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			case 'INP':
			case 'interactionToNextPaint':
				if ( $value <= 0.2 ) {
					$color_hex = $vivid_green_cyan;
				} elseif ( $value > 0.5 ) {
					$color_hex  = $vivid_red;
					$text_color = $white;
				} else {
					$color_hex = $luminous_vivid_amber;
				}
				break;
			default:
				$color_hex = '';
				break;
		}
		
		if ( ! empty( $color_hex ) ) {
			return "background-color: {$color_hex}; color: {$text_color};";
		} else {
			return '';
		}
	}
}

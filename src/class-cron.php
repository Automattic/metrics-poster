<?php

namespace MetricPoster;

use MetricPoster\NewRelicGQL;
use MetricPoster\AppModel;

class CronSetup {
    public function __construct() {
        \add_action( 'metric_poster_weekly_fetch', [ $this, 'fetch' ] );
    }

    public function run() {
        if ( ! \wp_next_scheduled( 'metric_poster_weekly_fetch' ) ) {
            // start on Monday at 12:00am.
            \wp_schedule_event( \strtotime( 'next monday midnight' ), 'weekly', 'metric_poster_weekly_fetch' );
        }
    }

    public function fetch() {

        // get posts of custom post type metric_posts.
        $posts = \get_posts( [
            'post_type' => 'metric_posts',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ] );

        // loop through posts.
        foreach ( $posts as $post ) {

            $app_id = \get_post_meta( $post->ID, 'appid', true );
            $week = get_prev_week_number();

            // initialize AppModel
            $app_info = \get_option('metric_poster_options[apps]')[$app_id];
            $app_info = new AppModel($app_info['app_name'], $app_id, $app_info['nr_id'], $app_info['nr_browser_guid'], $app_info['nr_app_guid'], $app_info['jp_blogid'], $app_info['app_template_file']);

            // Automate weekly metric fetch for the followin default.
            $metrics = ['error_count', 'warning_count'];
            if ( ! empty($app_info->get_jp_blogid())) {
                $metrics[] = 'jetpack_pageviews';
            }

            $facet = true;
            $year = get_correct_year();
            $metrics = implode(',', $metrics);
            $NR_ACCOUNT_ID = $app_info->get_nr_id();

            try{
                // Fetch metrics from NewRelic and build a metric object for DI.
                $nr_metrics = new NewRelicGQL($app_info, (int) $week, (int) $year, (int) $NR_ACCOUNT_ID, $metrics, (bool) $facet);
                $nr_metrics->get_results();

                // log success with app id and week number.
                error_log('Successfully fetched metrics for app id: ' . $app_id . ' for week: ' . $week);

            } catch (\Exception $e) {
                $error = $e->getMessage();
                error_log($error);
            }
        }
    }

}
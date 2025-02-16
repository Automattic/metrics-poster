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

            $tpl_file = empty($app_info['app_template_file']) ? 'post.tpl.html' : $app_info['app_template_file'];


            // initialize AppModel
            $app_info = \get_option('metric_poster_options[apps]')[$app_id];
            $app_info_model = new AppModel($app_info['app_name'], $app_id, $app_info['nr_id'], $app_info['nr_browser_guid'], $app_info['nr_app_guid'], $app_info['jp_blogid'], $tpl_file );

            // Automate weekly metric fetch for the followin default.
            $metrics = ['error_count', 'warning_count', 'cwv_extended', 'cwv_mobile_extended'];
            if ( ! empty($app_info_model->get_jp_blogid())) {
                $metrics[] = 'jetpack_pageviews';
            }

            $facet = true;
            $year = get_correct_year();
            $metrics = implode(',', $metrics);
            $NR_ACCOUNT_ID = $app_info_model->get_nr_id();

            try{
                // Fetch metrics from NewRelic and build a metric object for DI.
                $nr_metrics = new NewRelicGQL( $app_info_model, (int) $week, (int) $year, (int) $NR_ACCOUNT_ID, $metrics, (bool) $facet );
                $metric_results = $nr_metrics->get_results();

                $pg = new PostGenerator( GUTENBERG_TPL . '/' . $tpl_file, (int) $week, (int) $year, $metric_results, true, (string) $app_info['app_name'] );
                $output_post = $pg->create_post();

                if ( isset($output_post) ) {
                    // update post_content for existing post.
                    \wp_update_post( [
                        'ID' => $post->ID,
                        'post_content' => $output_post,
                    ] );
                }

                // log success with app id and week number.
                error_log('Successfully fetched metrics for app id: ' . $app_id . ' for week: ' . $week);

            } catch (\Exception $e) {
                $error = $e->getMessage();
                error_log($error);
            }
        }
    }

}
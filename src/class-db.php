<?php

namespace MetricPoster;

// class DB creates tables and handles database interactions.
class DB {
    // createTables creates the tables needed for the plugin.
    public static function createTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create the table for the posts.
        $table_name = $wpdb->prefix . 'metric_posts';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            past_php_errors text,
            past_php_warnings text,
            past_page_views text,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // insertPost inserts a post into the database.
    public static function insertPost($post_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'metric_posts';

        $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
            ]
        );
    }

    // updatePost updates a post in the database.
    public static function updatePost($post_id, Array $past_php_errors, Array $past_php_warnings, Array $past_page_views) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'metric_posts';

        // return if no post_id.
        if (!$post_id) {
            return;
        }

        // return if invalid arrays.
        if (!self::validateJson($past_php_errors) || !self::validateJson($past_php_warnings) || !self::validateJson($past_page_views)) {
            $error = new \WP_Error('metrics_custom_error_code', 'Invalid json entry on update.');
            throw $error;
        }

        $wpdb->update(
            $table_name,
            [
                'past_php_errors' => $past_php_errors ,
                'past_php_warnings' => $past_php_warnings,
                'past_page_views' => $past_page_views,
            ],
            [
                'post_id' => $post_id,
            ]
        );
    }

    // validate if valid json.
    public static function validateJson($json) {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    // getPost returns a post from the database.
    public static function getPost($post_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'metric_posts';

        $post = $wpdb->get_row("SELECT * FROM $table_name WHERE post_id = $post_id");

        return $post;
    }

    // getPosts returns all posts from the database.
    public static function getPosts() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'metric_posts';

        $posts = $wpdb->get_results("SELECT * FROM $table_name");

        return $posts;
    }
}
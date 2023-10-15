<?php

// WIP: this is not working yet.

declare(strict_types=1);

namespace MetricPoster\UI;

use MetricPoster\Utils;
use WP_List_Table;

class SettingsTable extends WP_List_Table
{



    public function __construct()
    {
        parent::__construct([
            'singular' => __('Metric Settings', 'metric-poster'),
            'plural' => __('Metric Settings', 'metric-poster'),
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'app_id' => __('App ID', 'metric-poster'),
            'app_name' => __('App Name', 'metric-poster'),
            'nr_id' => __('NR Account ID', 'metric-poster'),
            'nr_browser_guid' => __('NR Browser GUID', 'metric-poster'),
            'nr_app_guid' => __('NR App GUID', 'metric-poster'),
            'jp_blogid' => __('JP Blog ID', 'metric-poster'),
            'app_template_file' => __('Template', 'metric-poster')
        ];
    }

    public function prepare_items(): void
    {
        $this->process_bulk_action();

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $this->get_settings();
    }

    public function get_sortable_columns(): array
    {
        return [
            'app_name' => ['app_name', false],
            'app_template_file' => ['app_template_file', false]
        ];
    }

    public function column_default($item, $column_name): string
    {
        return (string) $item[$column_name] ?? '';
    }

    public function column_app_id($item): string
    {
        $actions = [
            'edit' => sprintf('<a href="admin.php?page=metric-poster-edit&appid=%s">Edit</a>', $item['id']),
            'delete' => sprintf('<a href="admin.php?page=metric-poster-app-settings&delete=%s">Delete</a>', $item['id']),
        ];

        return sprintf('%s %s', $item['app_id'], $this->row_actions($actions));
    }

    public function get_bulk_actions(): array
    {
        return [
            'delete' => 'Delete',
        ];
    }

    public function process_bulk_action(): void
    {
        if ('delete' === $this->current_action()) {
            $setting_id = $_REQUEST['setting'];

            // delete the setting.
            $settings = \get_option('metric_poster_options[apps]');
            unset($settings[$setting_id]);
            update_option('metric_poster_options[apps]', $settings);
        }
    }

    // bulk checkbox column.
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="setting[]" value="%s" />',
            $item['id']
        );
    }

    public function get_settings(): array
    {
        $settings = \get_option('metric_poster_options[apps]');

        if (!$settings) {
            return [];
        }

        // add app_id_key to each setting.
        foreach ($settings as $key => $setting) {
            $setting['id'] = $key;
            $setting['app_id'] = $key;
            $settings[$key] = $setting;
        }

        // remove keys for $settings.
        $settings = array_values($settings);

        return $settings;
    }

    public function render_settings_table(): void
    {
        $this->prepare_items();
        $this->display();
    }

    // display the settings table.
    public function display()
    {
        $this->display_tablenav('top');
?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php $this->print_column_headers(); ?>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php $this->display_rows_or_placeholder(); ?>
            </tbody>
            <tfoot>
                <tr>
                    <?php $this->print_column_headers(false); ?>
                </tr>
            </tfoot>
        </table>
<?php
        $this->display_tablenav('bottom');
    }
}

<?php

declare(strict_types=1);

namespace MetricPoster\UI;

use MetricPoster\NewRelicGQL;
use MetricPoster\AppModel;
use MetricPoster\PostGenerator;
use MetricPoster\UI\SettingsTable;

class SettingsPage
{

    public function __construct()
    {
    }

    public function run(): void
    {
        // create a plugin options page with sub settings page.
        \add_action('admin_menu', array($this, 'metric_poster_settings'));
        \add_action('admin_init', array($this, 'metric_poster_register_settings'));
    }

    function metric_poster_register_settings()
    {
        \register_setting('metric_poster_options', 'metric_results');
    }

    function metric_poster_settings()
    {
        \add_menu_page('Metric Poster', 'Metric Poster', 'manage_options', 'metric-poster', array($this, 'metric_poster_options'), 'dashicons-chart-bar', 6);
        \add_submenu_page('metric-poster', 'Metric Poster', 'Main', 'manage_options', 'metric-poster', array($this, 'metric_poster_options'));

        // add edit page and make unlisted.
        \add_submenu_page(null, 'Metric Poster Edit', 'Edit', 'manage_options', 'metric-poster-edit', array($this, 'metric_poster_edit_page'));
        \add_submenu_page('metric-poster', 'Metric Poster Settings', 'Settings', 'manage_options', 'metric-poster-app-settings', array($this, 'metric_poster_settings_page'));
    }

    function metric_poster_edit_page()
    {
        $this->metric_settings_update_logic();

        // on load, get options.
        if (isset($_GET['appid'])) {
            $metric_poster_options = \get_option('metric_poster_options[apps]', array());
            $app_id = $_GET['appid'];
            $app_info = $metric_poster_options[$app_id] ?? [];
        }

        // on update, display wordpress updated message toast.
        if (isset($_GET['updated'])) {
?>
            <div class="notice notice-success is-dismissible">
                <p>App updated.</p>
            </div>
        <?php
        }
        ?>
        <div class="wrap">
            <h1>Metric Poster Settings</h1>
            <style>
                #metric--settings {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 40px;
                }

                #metric--settings th,
                #metric--settings td {
                    border: 1px solid #ccc;
                    padding: 5px;
                }

                #metric--settings th {
                    text-align: left;
                }

                #app--entry__container {
                    border-collapse: collapse;
                    margin-bottom: 40px;
                }

                #app--entry__container td {
                    border: 1px solid #ccc;
                    padding: 5px;
                }

                #app--entry__container td:first-child {
                    width: 140px;
                }

                #app--entry__container input {
                    width: 100%;
                }

                #app--entry__container label {
                    display: inline-block;
                }

                #app--entry__container label+span {
                    display: inline-block;
                }

                #app--entry__container input[type="submit"]:hover {
                    cursor: pointer;
                }
            </style>
            <form method="post" action="">
                <?php
                \settings_fields('metric_poster_options');
                \do_settings_sections('metric_poster_options'); ?>


                <h2>App Edit</h2>
                <?php
                $this->metric_app_entry_markup($app_info, true);

                \submit_button();
                ?>
            </form>
        <?php


    }

    function metric_app_entry_markup($app_info = null, $is_edit_screen = false)
    {
        ?>
            <table id="app--entry__container">
                <tr>
                    <td>
                        <label for="metric_poster_options[app_id]">App ID</label>
                        <span class="dashicons dashicons-editor-help" title="The App ID is the unique identifier used for WPVIP applications."></span>
                    </td>
                    <td>
                        <input type="text" name="metric_poster_options[app_id]" value="<?php echo $_GET['appid'] ?? ''; ?>" <?php echo $is_edit_screen ? 'readonly' : ''; ?>>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="metric_poster_options[app_name]">App Name</label>
                        <span class="dashicons dashicons-editor-help" title="This name will be appended at the end of your P2 titles."></span>
                    </td>
                    <td>
                        <input type="text" name="metric_poster_options[app_name]" value="<?php echo $app_info['app_name'] ?? ''; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="metric_poster_options[nr_id]">NR Account ID</label>
                        <span class="dashicons dashicons-editor-help" title="Copy/paste this value from the NR application's meta modal."></span>
                    </td>
                    <td>
                        <input type="text" name="metric_poster_options[nr_id]" value="<?php echo $app_info['nr_id'] ?? ''; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="metric_poster_options[nr_browser_guid]">NR Browser GUID</label>
                    </td>
                    <td>
                        <input type="text" name="metric_poster_options[nr_browser_guid]" value="<?php echo $app_info['nr_browser_guid'] ?? ''; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="metric_poster_options[nr_app_guid]">NR App GUID</label>
                    </td>
                    <td>
                        <input type="text" name="metric_poster_options[nr_app_guid]" value="<?php echo $app_info['nr_app_guid'] ?? ''; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="metric_poster_options[jp_blogid]">JP Blog ID</label>
                    </td>
                    <td>
                        <input type="text" name="metric_poster_options[jp_blogid]" value="<?php echo $app_info['jp_blogid'] ?? ''; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="metric_poster_options[app_template_file]">Template</label>
                    </td>
                    <td>
                        <select name="metric_poster_options[app_template_file]">
                            <?php
                            $templates = scandir(GUTENBERG_TPL);

                            $tpl_val = $app_info['app_template_file'] ?? '';

                            // if empty, echo placeholder option.
                            if (empty($tpl_val)) {
                                echo '<option value="">Default post.tpl.html</option>';
                            }

                            foreach ($templates as $template) {

                                // if file name prefix is not 'post', skip.
                                if (substr($template, 0, 4) !== 'post') {
                                    continue;
                                }

                                if ($template === '.' || $template === '..') {
                                    continue;
                                }
                            ?>
                                <option <?php \selected($tpl_val, $template); ?> value="<?php echo $template; ?>"><?php echo $template; ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

            </table>

        <?php

    }

    function metric_settings_update_logic(): void
    {
        // check user capabilities.
        if (!\current_user_can('manage_options')) {
            return;
        }

        // on save, update options.
        if (isset($_POST['metric_poster_options']) && isset($_POST['metric_poster_options']['app_id'])) {
            $metric_poster_options = \get_option('metric_poster_options[apps]', array());

            $app_id = $_POST['metric_poster_options']['app_id'];

            $metric_poster_options[$app_id] = [];

            $metric_poster_options[$app_id]['app_name'] = $_POST['metric_poster_options']['app_name'];
            $metric_poster_options[$app_id]['nr_id'] = $_POST['metric_poster_options']['nr_id'];
            $metric_poster_options[$app_id]['nr_browser_guid'] = $_POST['metric_poster_options']['nr_browser_guid'];
            $metric_poster_options[$app_id]['nr_app_guid'] = $_POST['metric_poster_options']['nr_app_guid'];
            $metric_poster_options[$app_id]['jp_blogid'] = $_POST['metric_poster_options']['jp_blogid'];
            $metric_poster_options[$app_id]['app_template_file'] = $_POST['metric_poster_options']['app_template_file'];
            \update_option('metric_poster_options[apps]', $metric_poster_options);
        }

        // on delete, remove option.
        if (isset($_GET['delete'])) {
            $metric_poster_options = \get_option('metric_poster_options[apps]', array());
            unset($metric_poster_options[$_GET['delete']]);
            \update_option('metric_poster_options[apps]', $metric_poster_options);
        }

        // on edit, redirect to edit page.
        if (isset($_GET['edit'])) {
            \wp_redirect(\admin_url('admin.php?page=metric-poster-edit&app_id=' . $_GET['edit']));
        }
    }

    function metric_poster_settings_page()
    {

        $this->metric_settings_update_logic();

        ?>
            <div class="wrap">
                <h1>Metric Poster Settings</h1>
                <style>

                    #app--entry__container {
                        border-collapse: collapse;
                        margin-bottom: 40px;
                    }

                    #app--entry__container td {
                        border: 1px solid #ccc;
                        padding: 5px;
                    }

                    #app--entry__container td:first-child {
                        width: 140px;
                    }

                    #app--entry__container input {
                        width: 100%;
                    }

                    #app--entry__container label {
                        display: inline-block;
                    }

                    #app--entry__container label+span {
                        display: inline-block;
                    }

                    #app--entry__container input[type="submit"]:hover {
                        cursor: pointer;
                    }
                </style>

                <?php
                $wp_list_table = new SettingsTable();
                $wp_list_table->prepare_items();
                $wp_list_table->display();
                ?>


                <form method="post" action="">
                    <?php
                    \settings_fields('metric_poster_options');
                    \do_settings_sections('metric_poster_options'); ?>

                    <h2>App Entry</h2>
                    <?php
                    $this->metric_app_entry_markup();

                    \submit_button();
                    ?>
                </form>
            </div>
        <?php
    }



    function metric_poster_options()
    {

        if (!\current_user_can('manage_options')) {
            \wp_die('You do not have sufficient permissions to access this page.');
        }

        // on post check metric_poster_options nonce.
        if (isset($_POST['metric_poster_options_nonce'])) {
            // verify nonce.
            if (!\wp_verify_nonce($_POST['metric_poster_options_nonce'], 'metric_poster_options')) {
                \wp_die('Nonce verification failed.');
            }
        }

        // if $_POST['metric_poster_options'] is set, do stuff.
        if (isset($_POST['metric_poster_options'])) {
            // get metric_poster_options[app_name] value.
            $app_id = $_POST['metric_poster_options']['app_name'];
            $week = $_POST['metric_poster_options']['week'];

            // initialize AppModel
            $app_info = \get_option('metric_poster_options[apps]')[$app_id];
            $app_info = new AppModel($app_info['app_name'], $app_id, $app_info['nr_id'], $app_info['nr_browser_guid'], $app_info['nr_app_guid'], $app_info['jp_blogid'], $app_info['app_template_file']);

            // create comma separated list of metrics from $_POST['metric_poster_options']['metrics'] array.
            $metrics = [];
            foreach ($_POST['metric_poster_options']['metrics'] as $metric) {
                switch ($metric) {
                    case 'jetpack_pageviews':

                        // if $app_info['jp_blogid'] is not set, skip.
                        if (empty($app_info->get_jp_blogid())) {
                            continue;
                        }

                        $metrics[] = 'jetpack_pageviews';

                        break;
                    case 'errors':
                        $metrics[] = 'error_count';
                        $metrics[] = 'errors';
                        break;
                    case 'warnings':
                        $metrics[] = 'warning_count';
                        $metrics[] = 'warnings';
                        break;
                    case 'cwv':
                        $metrics[] = 'cwv';
                        $metrics[] = 'cwv_chart';
                        break;
                    default:
                        $metrics[] = $metric;
                }
            }

            $show_headings = true;
            $facet = true;
            $year = date('Y');
            $metrics = implode(',', $metrics);
            $NR_ACCOUNT_ID = $app_info->get_nr_id();
            $template_file = $app_info->get_template_file();

            // Fetch metrics from NewRelic and build a metric object for DI.
            $nr_metrics = new NewRelicGQL($app_info, (int) $week, (int) $year, (int) $NR_ACCOUNT_ID, $metrics, (bool) $facet);
            $metric_results = $nr_metrics->get_results();
            $pg = new PostGenerator(GUTENBERG_TPL . '/' . $template_file, (int) $week, (int) $year, $metric_results, (bool) $show_headings, (string) $app_info->get_app_name());
            $output_post = $pg->create_post();
        }



        ?>
            <div class="wrap">
                <h2>Metric Poster</h2>
                <form id="metrics--form" method="post" action="">
                    <?php
                    \settings_fields('metric_poster_options');
                    \do_settings_sections('metric_poster_options');
                    // do nonce field.
                    \wp_nonce_field('metric_poster_options', 'metric_poster_options_nonce');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Application</th>
                            <td>
                                <select name="metric_poster_options[app_name]">
                                    <?php
                                    $metric_poster_options = \get_option('metric_poster_options[apps]');
                                    // echo placeholder option.
                                    echo '<option value="">Select an app</option>';
                                    foreach ($metric_poster_options as $key => $value) {
                                    ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value['app_name']; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Week</th>
                            <td>
                                <select name="metric_poster_options[week]">
                                    <option value="<?php echo get_prev_week_number(); ?>"><?php echo get_prev_week_number(); ?></option>
                                    <option value="<?php echo date('W') - 2; ?>"><?php echo date('W') - 2; ?></option>
                                </select>
                            </td>
                        </tr>
                        <style>
                            input[type="checkbox"] {
                                margin-right: 4px;
                            }

                            input[type="checkbox"]+label {
                                margin-right: 8px;
                            }
                        </style>
                        <tr>
                            <th scope="row">Metrics</th>
                            <td>
                                <input type="checkbox" name="metric_poster_options[metrics][]" value="jetpack_pageviews" <?php \checked(1, false, false); ?> />
                                <label for="metric_poster_options[metrics][]">JP Page Views</label>

                                <input type="checkbox" name="metric_poster_options[metrics][]" value="errors" <?php \checked(1, true, true); ?> />
                                <label for="metric_poster_options[metrics][]">PHP Errors</label>

                                <input type="checkbox" name="metric_poster_options[metrics][]" value="warnings" <?php \checked(1, true, true); ?> />
                                <label for="metric_poster_options[metrics][]">PHP Warnings</label>

                                <input type="checkbox" name="metric_poster_options[metrics][]" value="transactions" <?php \checked(1, true, true); ?> />
                                <label for="metric_poster_options[metrics][]">Top Slow Transactions</label>

                                <input type="checkbox" name="metric_poster_options[metrics][]" value="cwv" <?php \checked(1, true, true); ?> />
                                <label for="metric_poster_options[metrics][]">CWV Chart</label>

                                <input type="checkbox" name="metric_poster_options[metrics][]" value="404s" <?php \checked(1, false, false); ?> />
                                <label for="metric_poster_options[metrics][]">404 Errors</label>

                                <input type="checkbox" name="metric_poster_options[metrics][]" value="500s" <?php \checked(1, false, false); ?> />
                                <label for="metric_poster_options[metrics][]">500 Errors</label>
                            </td>
                        </tr>

                        <?php
                        // if $output_post is set, show the results.
                        if (isset($output_post)) {
                        ?>
                            <tr>
                                <th scope="row">Output</th>
                                <td>
                                    <button type="button" id="copy_to_clipboard">Copy to Clipboard</button>
                                    <pre><textarea id="p2_output" rows="20" cols="100" name="metric_poster_options[output_post]"><?php echo \esc_attr($output_post); ?></textarea></pre>
                                </td>
                            </tr>
                            <!-- script for copy to clipboard on click event for element #p2_output -->
                            <script>
                                // on dom ready
                                document.addEventListener('DOMContentLoaded', function() {
                                    // on click of #copy_to_clipboard
                                    document.getElementById('copy_to_clipboard').addEventListener('click', function() {
                                        // select the text in #p2_output
                                        document.getElementById('p2_output').select();
                                        // copy the text to the clipboard
                                        document.execCommand('copy');
                                        alert('copied to clipboard');
                                    });
                                });
                            </script>
                        <?php
                        }
                        ?>
                    </table>
                    <?php \submit_button('Get Metrics'); ?>
                </form>

                <!-- loading spinner animation -->
                <style>
                    #loading_spinner {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0, 0, 0, 0.5);
                        z-index: 9999;
                    }

                    .lds-ripple {
                        display: inline-block;
                        position: relative;
                        width: 80px;
                        height: 80px;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                    }

                    .lds-ripple div {
                        position: absolute;
                        border: 4px solid #fff;
                        opacity: 1;
                        border-radius: 50%;
                        animation: lds-ripple 1s cubic-bezier(0, 0.2, 0.8, 1) infinite;
                    }

                    .lds-ripple div:nth-child(2) {
                        animation-delay: -0.5s;
                    }

                    @keyframes lds-ripple {
                        0% {
                            top: 36px;
                            left: 36px;
                            width: 0;
                            height: 0;
                            opacity: 0;
                        }

                        4.9% {
                            top: 36px;
                            left: 36px;
                            width: 0;
                            height: 0;
                            opacity: 0;
                        }

                        5% {
                            top: 36px;
                            left: 36px;
                            width: 0;
                            height: 0;
                            opacity: 1;
                        }

                        100% {
                            top: 0px;
                            left: 0px;
                            width: 72px;
                            height: 72px;
                            opacity: 0;
                        }
                    }
                </style>
                <div id="loading_spinner">
                    <div class="lds-ripple">
                        <div></div>
                        <div></div>
                    </div>
                </div>

                <!-- script for loading spinner -->
                <script>
                    // on dom ready
                    document.addEventListener('DOMContentLoaded', function() {
                        // on click of #metrics--form submit button
                        document.getElementById('metrics--form').addEventListener('submit', function() {
                            // show #loading_spinner
                            document.getElementById('loading_spinner').style.display = 'block';
                        });
                    });
                </script>



            </div>
    <?php
    }
}

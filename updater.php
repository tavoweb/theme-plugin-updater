<?php

if (!defined('ABSPATH')) {

    exit;

}



class Theme_Plugin_Updater {



    private $github_username;



    public function __construct() {

        $this->github_username = get_option('tpu_github_username', 'tavoweb'); // GitHub vartotojo vardas



        add_filter('pre_set_site_transient_update_themes', array($this, 'check_theme_updates'));

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_plugin_updates'));



        // Pridėti admin puslapį

        add_action('admin_menu', array($this, 'add_admin_page'));

    }



    /**

     * Pridėti admin puslapį.

     */

    public function add_admin_page() {

        add_options_page(

            'Theme & Plugin Updater',

            'Theme & Plugin Updater',

            'manage_options',

            'theme-plugin-updater',

            array($this, 'render_admin_page')

        );

    }



    /**

     * Atvaizduoti admin puslapį.

     */

    public function render_admin_page() {

        if (!current_user_can('manage_options')) {

            return;

        }



        $github_username = get_option('tpu_github_username', '');

        if (empty($github_username)) {

            echo '<div class="notice notice-warning"><p>GitHub vartotojo vardas nenustatytas. Prašome įvesti jį <a href="' . admin_url('options-general.php?page=theme-plugin-updater') . '">nustatymuose</a>.</p></div>';

            return;

        }



        // Gauti temas ir papildinius

        $themes = $this->get_themes_info();

        $plugins = $this->get_plugins_info();



        echo '<div class="wrap">';

        echo '<h1>Theme & Plugin Updater</h1>';

        echo '<table class="widefat">';

        echo '<thead><tr><th>Type</th><th>Slug</th><th>Installed Version</th><th>GitHub Version</th><th>Status</th><th>Action</th></tr></thead>';

        echo '<tbody>';



        // Rodyti temas

        foreach ($themes as $theme) {

            $github_version = $this->get_latest_github_release($theme['slug']);

            $status = ($github_version && version_compare($theme['version'], $github_version, '<')) ? '⚠️ Update available' : '✅ Up to date';

            $action = ($status === '⚠️ Update available') ? '<a href="' . admin_url('update-core.php') . '">Update</a>' : '';



            echo '<tr>';

            echo '<td>Theme</td>';

            echo '<td>' . esc_html($theme['slug']) . '</td>';

            echo '<td>' . esc_html($theme['version']) . '</td>';

            echo '<td>' . esc_html($github_version ? $github_version : 'Not found') . '</td>';

            echo '<td>' . esc_html($status) . '</td>';

            echo '<td>' . $action . '</td>';

            echo '</tr>';

        }



        // Rodyti papildinius

        foreach ($plugins as $plugin) {

            $github_version = $this->get_latest_github_release($plugin['slug']);

            $status = ($github_version && version_compare($plugin['version'], $github_version, '<')) ? '⚠️ Update available' : '✅ Up to date';

            $action = ($status === '⚠️ Update available') ? '<a href="' . admin_url('update-core.php') . '">Update</a>' : '';



            echo '<tr>';

            echo '<td>Plugin</td>';

            echo '<td>' . esc_html($plugin['slug']) . '</td>';

            echo '<td>' . esc_html($plugin['version']) . '</td>';

            echo '<td>' . esc_html($github_version ? $github_version : 'Not found') . '</td>';

            echo '<td>' . esc_html($status) . '</td>';

            echo '<td>' . $action . '</td>';

            echo '</tr>';

        }



        echo '</tbody>';

        echo '</table>';

        echo '</div>';

    }



    /**

     * Gauti informaciją apie temas.

     */

    private function get_themes_info() {

        $themes = [];

        $theme_data = wp_get_theme();

        $themes[] = [

            'slug' => $theme_data->get_template(),

            'version' => $theme_data->get('Version'),

        ];

        return $themes;

    }



    /**

     * Gauti informaciją apie papildinius.

     */

    private function get_plugins_info() {

        $plugins = [];

        $installed_plugins = get_plugins();

        foreach ($installed_plugins as $plugin_file => $plugin_data) {

            $plugins[] = [

                'slug' => dirname($plugin_file),

                'version' => $plugin_data['Version'],

            ];

        }

        return $plugins;

    }



    /**

     * Gauti naujausią GitHub saugyklos versiją.

     */

    private function get_latest_github_release($slug) {

        $url = "https://api.github.com/repos/{$this->github_username}/{$slug}/releases/latest";

        $args = array(

            'headers' => array(

                'User-Agent' => 'WordPress-Theme-Plugin-Updater', // GitHub reikalauja User-Agent

            ),

        );



        $response = wp_remote_get($url, $args);



        if (is_wp_error($response)) {

            error_log('GitHub klaida: ' . $response->get_error_message());

            return false;

        }



        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body);



        if (json_last_error() !== JSON_ERROR_NONE) {

            error_log('GitHub API atsakymo klaida: ' . json_last_error_msg());

            return false;

        }



        return $data->tag_name; // Grąžina naujausią versiją (pvz., v1.0.0)

    }



    /**

     * Tikrina temų atnaujinimus.

     */

    public function check_theme_updates($transient) {

        if (empty($transient->checked)) {

            return $transient;

        }



        $theme_data = wp_get_theme();

        $theme_slug = $theme_data->get_template();

        $current_version = $theme_data->get('Version');



        $latest_version = $this->get_latest_github_release($theme_slug);



        if ($latest_version && version_compare($current_version, $latest_version, '<')) {

            $transient->response[$theme_slug] = array(

                'theme' => $theme_slug,

                'new_version' => $latest_version,

                'url' => "https://github.com/{$this->github_username}/{$theme_slug}",

                'package' => "https://github.com/{$this->github_username}/{$theme_slug}/releases/download/{$latest_version}/{$theme_slug}.zip",

            );

        }



        return $transient;

    }



    /**

     * Tikrina papildinių atnaujinimus.

     */

    public function check_plugin_updates($transient) {

        if (empty($transient->checked)) {

            return $transient;

        }



        $plugins = get_plugins();

        foreach ($plugins as $plugin_file => $plugin_data) {

            $plugin_slug = dirname($plugin_file);

            $current_version = $plugin_data['Version'];



            $latest_version = $this->get_latest_github_release($plugin_slug);



            if ($latest_version && version_compare($current_version, $latest_version, '<')) {

                $transient->response[$plugin_file] = (object) array(

                    'slug' => $plugin_slug,

                    'new_version' => $latest_version,

                    'url' => $plugin_data['PluginURI'],

                    'package' => "https://github.com/{$this->github_username}/{$plugin_slug}/releases/download/{$latest_version}/{$plugin_slug}.zip",

                );

            }

        }



        return $transient;

    }
/**
 * Pervadina temos arba papildinio aplanką po atnaujinimo.
 */
public function rename_theme_folder_after_update($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'theme') {
        $theme_slug = 'neptune-by-osetin'; // Nurodykite norimą temos aplanko pavadinimą
        $old_theme_folder = $upgrader_object->result['destination_name']; // Senas aplanko pavadinimas
        $theme_path = WP_CONTENT_DIR . '/themes/'; // Temų katalogas

        // Jei senas aplankas egzistuoja ir naujas aplankas dar neegzistuoja, pervadinkite
        if (is_dir($theme_path . $old_theme_folder) && !is_dir($theme_path . $theme_slug)) {
            rename($theme_path . $old_theme_folder, $theme_path . $theme_slug);
        }
    }
}
public function __construct() {
    $this->github_username = get_option('tpu_github_username', 'tavoweb'); // GitHub vartotojo vardas

    add_filter('pre_set_site_transient_update_themes', array($this, 'check_theme_updates'));
    add_filter('pre_set_site_transient_update_plugins', array($this, 'check_plugin_updates'));

    // Pridėti veiksmą, kad pervadintų temų aplankus po atnaujinimo
    add_action('upgrader_post_install', array($this, 'rename_theme_folder_after_update'), 10, 2);
}
}
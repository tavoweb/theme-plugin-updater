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

        // Pridėti veiksmą, kad pervadintų temų aplankus po atnaujinimo
        add_action('upgrader_post_install', array($this, 'rename_theme_folder_after_update'), 10, 2);

        // Pridėti AJAX veiksmą, kad būtų galima patikrinti atnaujinimus iš naujo
        add_action('wp_ajax_tpu_check_updates', array($this, 'ajax_check_updates'));
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

        // Mygtukas "Patikrinti iš naujo"
        echo '<button id="tpu-check-updates" class="button button-primary">Patikrinti iš naujo</button>';
        echo '<span id="tpu-check-updates-spinner" class="spinner" style="float: none; margin-left: 10px;"></span>';
        echo '<p id="tpu-check-updates-message"></p>';

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

        // JavaScript, skirtas mygtuko "Patikrinti iš naujo" funkcionalumui
        echo '
        <script>
        jQuery(document).ready(function($) {
            $("#tpu-check-updates").on("click", function() {
                var button = $(this);
                var spinner = $("#tpu-check-updates-spinner");
                var message = $("#tpu-check-updates-message");

                button.prop("disabled", true);
                spinner.addClass("is-active");

                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "tpu_check_updates"
                    },
                    success: function(response) {
                        if (response.success) {
                            message.html("<div class=\'notice notice-success\'><p>" + response.data.message + "</p></div>");
                            location.reload(); // Perkrauti puslapį, kad būtų rodomi naujausi duomenys
                        } else {
                            message.html("<div class=\'notice notice-error\'><p>" + response.data.message + "</p></div>");
                        }
                    },
                    error: function() {
                        message.html("<div class=\'notice notice-error\'><p>Įvyko klaida. Bandykite dar kartą.</p></div>");
                    },
                    complete: function() {
                        button.prop("disabled", false);
                        spinner.removeClass("is-active");
                    }
                });
            });
        });
        </script>
        ';
    }

    /**
     * AJAX veiksmas, skirtas patikrinti atnaujinimus iš naujo.
     */
    public function ajax_check_updates() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Neturite teisių atlikti šį veiksmą.'));
        }

        // Išvalyti WordPress atnaujinimų transiento duomenis
        delete_site_transient('update_themes');
        delete_site_transient('update_plugins');

        wp_send_json_success(array('message' => 'Atnaujinimai sėkmingai patikrinti.'));
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

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 404) {
            error_log('GitHub repo nerastas: ' . $slug);
            return false;
        }

        if ($response_code !== 200) {
            error_log('GitHub API klaida: Negalima gauti duomenų. HTTP kodas: ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('GitHub API atsakymo klaida: ' . json_last_error_msg());
            return false;
        }

        if (empty($data->tag_name)) {
            error_log('GitHub API klaida: Nerastas tag_name.');
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
     * Pervadina temos aplanką po atnaujinimo.
     */
    public function rename_theme_folder_after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'theme') {
            // Gauti originalų temos slug'ą
            $theme_slug = $upgrader_object->skin->theme_info->get_template(); // Originalus slug (pvz., neptune-by-osetin)
            $theme_path = WP_CONTENT_DIR . '/themes/'; // Temų katalogas

            // Gauti laikiną aplanko pavadinimą
            $temp_folder = $upgrader_object->result['destination_name']; // Laikinas aplankas (pvz., tavoweb-neptune-by-osetin-02224f2)

            // Jei laikinas aplankas egzistuoja, pervadinkite jį atgal į originalų pavadinimą
            if (is_dir($theme_path . $temp_folder) && !is_dir($theme_path . $theme_slug)) {
                rename($theme_path . $temp_folder, $theme_path . $theme_slug);
            }
        }
    }
}
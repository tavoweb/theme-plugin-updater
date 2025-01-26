<?php

/*

Plugin Name: Theme & Plugin Updater
Description: Checks for updates in GitHub repositories and allows updating themes and plugins.
Version: 1.0
Text Domain: theme-plugin-updater
Author: TavoWEB
Author URI: https://tavoweb.lt
*/



// Prevent direct access

if (!defined('ABSPATH')) {

    exit;

}



// Include necessary files

require_once plugin_dir_path(__FILE__) . 'updater.php';



// Initialize the updater

new Theme_Plugin_Updater();



// Add settings page

add_action('admin_menu', 'tpu_add_settings_page');

function tpu_add_settings_page() {

    add_options_page(

        'Theme & Plugin Updater Settings',

        'Theme & Plugin Updater',

        'manage_options',

        'theme-plugin-updater',

        'tpu_render_settings_page'

    );

}



// Render settings page

function tpu_render_settings_page() {

    if (!current_user_can('manage_options')) {

        return;

    }



    // Check if GitHub username is set

    $github_username = get_option('tpu_github_username', '');



    ?>

    <div class="wrap">

        <h1>Theme & Plugin Updater Settings</h1>

        <form method="post" action="options.php">

            <?php

            settings_fields('tpu_options_group');

            do_settings_sections('theme-plugin-updater');

            submit_button();

            ?>

        </form>

    </div>

    <?php

}



// Register settings

add_action('admin_init', 'tpu_register_settings');

function tpu_register_settings() {

    register_setting('tpu_options_group', 'tpu_github_username');



    add_settings_section('tpu_main_section', 'GitHub Settings', null, 'theme-plugin-updater');



    add_settings_field(

        'tpu_github_username',

        'GitHub Username',

        'tpu_github_username_callback',

        'theme-plugin-updater',

        'tpu_main_section'

    );

}



// Callback function for settings field

function tpu_github_username_callback() {

    $username = get_option('tpu_github_username', '');

    echo '<input type="text" name="tpu_github_username" value="' . esc_attr($username) . '" class="regular-text">';

}
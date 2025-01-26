<?php
/*
Plugin Name: Theme & Plugin Updater
Description: Checks for updates in GitHub repositories and allows updating themes and plugins.
Version: 1.3
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
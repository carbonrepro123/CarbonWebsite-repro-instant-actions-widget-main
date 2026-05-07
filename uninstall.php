<?php
/**
 * Uninstall Carbon Repro Instant Actions Widget
 *
 * This file is called when the plugin is deleted.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete analytics table
$table_name = $wpdb->prefix . 'criaw_analytics';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Delete plugin settings
delete_option('criaw_settings');
delete_option('criaw_widget_stats');

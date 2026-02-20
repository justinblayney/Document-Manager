<?php
/**
 * Uninstall Document Manager
 *
 * Runs when the plugin is deleted from WP Admin → Plugins.
 * Removes all plugin options and cleans up transients.
 * Does NOT delete uploaded client files — that data belongs to the site owner.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove plugin settings
delete_option('cdm_upload_root');
delete_option('cdm_max_file_size');
delete_option('cdm_allowed_types');

// Remove rate-limit transients (they expire on their own, but clean up immediately)
// No WP API alternative exists for wildcard transient deletion by key pattern.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_cdm_uploads_%'
        OR option_name LIKE '_transient_timeout_cdm_uploads_%'"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

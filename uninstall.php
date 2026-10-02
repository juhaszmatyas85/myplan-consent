<?php
/**
 * Removes the settings, the log table and the purge job.
 *
 * @package myplan-consent
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'myplan_consent_settings' );
delete_option( 'myplan_consent_db' );
wp_clear_scheduled_hook( 'myplan_consent_purge' );

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mpc_consent_log' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

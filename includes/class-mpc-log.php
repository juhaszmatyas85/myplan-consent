<?php
/**
 * Consent log: proof of consent (GDPR art. 7(1)).
 *
 * Each decision is stored with the random consent ID that also sits in the
 * visitor's cookie, the choices, the policy version and the page. No IP
 * address or user agent is stored: the ID is enough to show which decision a
 * given browser made, and it says nothing about the person.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log.
 */
class MPC_Log {

	const DB_VERSION = 1;
	const CRON       = 'myplan_consent_purge';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'mpc_consent_log';
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_mpc_log', array( __CLASS__, 'record' ) );
		add_action( 'wp_ajax_nopriv_mpc_log', array( __CLASS__, 'record' ) );
		add_action( self::CRON, array( __CLASS__, 'purge' ) );

		if ( (int) get_option( 'myplan_consent_db' ) !== self::DB_VERSION ) {
			self::install();
		}

		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Creates or updates the table.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			'CREATE TABLE ' . self::table() . ' (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_id char(36) NOT NULL,
				created datetime NOT NULL,
				policy_version int(10) unsigned NOT NULL,
				action varchar(16) NOT NULL,
				functional tinyint(1) NOT NULL DEFAULT 0,
				analytics tinyint(1) NOT NULL DEFAULT 0,
				marketing tinyint(1) NOT NULL DEFAULT 0,
				page varchar(255) NOT NULL DEFAULT \'\',
				PRIMARY KEY  (id),
				KEY consent_id (consent_id),
				KEY created (created)
			) ' . $wpdb->get_charset_collate() . ';'
		);

		// Autoloaded: init() reads it on every request.
		update_option( 'myplan_consent_db', self::DB_VERSION, true );
	}

	/**
	 * Stops the purge job.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Endpoint URL the banner reports to.
	 *
	 * admin-ajax rather than the REST API: many hardened sites (this server
	 * included) refuse anonymous REST requests, and a consent log that
	 * silently fails is worse than none.
	 *
	 * @return string
	 */
	public static function endpoint() {
		return add_query_arg( 'action', 'mpc_log', admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Stores one decision (JSON body).
	 */
	public static function record() {
		global $wpdb;

		if ( ! MPC_Settings::get( 'log_enabled' ) ) {
			wp_send_json( null, 204 );
		}

		$data = json_decode( (string) file_get_contents( 'php://input' ), true );

		if ( ! is_array( $data )
			|| empty( $data['cid'] ) || ! is_string( $data['cid'] ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $data['cid'] )
			|| ! isset( $data['version'] ) || ! is_numeric( $data['version'] )
			|| empty( $data['action'] ) || ! in_array( $data['action'], array( 'accept', 'reject', 'custom', 'embed', 'gpc' ), true )
			|| ! isset( $data['choices'] ) || ! is_array( $data['choices'] ) ) {
			wp_send_json( null, 400 );
		}

		// A light brake against someone filling the table from a script: at
		// most 20 entries a minute per address. Only a hash is kept, briefly.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'mpc_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$count = (int) get_transient( $key );

		if ( $count >= 20 ) {
			wp_send_json( null, 429 );
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		$choices = $data['choices'];
		$page    = isset( $data['page'] ) && is_string( $data['page'] ) ? (string) wp_parse_url( $data['page'], PHP_URL_PATH ) : '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'consent_id'     => strtolower( $data['cid'] ),
				'created'        => current_time( 'mysql', true ),
				'policy_version' => (int) $data['version'],
				'action'         => $data['action'],
				'functional'     => empty( $choices['functional'] ) ? 0 : 1,
				'analytics'      => empty( $choices['analytics'] ) ? 0 : 1,
				'marketing'      => empty( $choices['marketing'] ) ? 0 : 1,
				'page'           => substr( sanitize_text_field( $page ), 0, 255 ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s' )
		);

		wp_send_json( null, 204 );
	}

	/**
	 * Deletes entries past the retention period.
	 */
	public static function purge() {
		global $wpdb;

		$days = (int) MPC_Settings::get( 'log_retention_days' );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Decision counts for the last N days.
	 *
	 * @param int $days Days.
	 * @return array<string, int>
	 */
	public static function stats( $days = 30 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'SELECT action, COUNT(*) AS n FROM ' . self::table() . ' WHERE created >= %s GROUP BY action', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$out = array_fill_keys( array( 'accept', 'reject', 'custom', 'embed', 'gpc' ), 0 );

		foreach ( (array) $rows as $row ) {
			$out[ $row['action'] ] = (int) $row['n'];
		}

		return $out;
	}

	/**
	 * Streams the log as CSV.
	 */
	public static function export_csv() {
		global $wpdb;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'consent_id', 'created_utc', 'policy_version', 'action', 'functional', 'analytics', 'marketing', 'page' ) );

		$offset = 0;

		do {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( 'SELECT consent_id, created, policy_version, action, functional, analytics, marketing, page FROM ' . self::table() . ' ORDER BY id LIMIT %d, 1000', $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_N
			);

			foreach ( $rows as $row ) {
				fputcsv( $out, $row );
			}

			$offset += 1000;
		} while ( count( $rows ) === 1000 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}

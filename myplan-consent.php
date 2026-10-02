<?php
/**
 * Plugin Name:       MyPlan Cookie Consent
 * Description:       GDPR cookie banner with Google Consent Mode v2 (advanced and basic), script and embed blocking, consent log and WP Consent API support. Translatable with gettext, WPML, Polylang and TranslatePress.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            MyPlan
 * License:           GPL-2.0-or-later
 * Text Domain:       myplan-consent
 * Domain Path:       /languages
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

define( 'MPC_VERSION', '1.0.0' );
define( 'MPC_FILE', __FILE__ );
define( 'MPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPC_URL', plugin_dir_url( __FILE__ ) );

require MPC_DIR . 'includes/class-mpc-settings.php';
require MPC_DIR . 'includes/class-mpc-texts.php';
require MPC_DIR . 'includes/class-mpc-frontend.php';
require MPC_DIR . 'includes/class-mpc-blocker.php';
require MPC_DIR . 'includes/class-mpc-log.php';
require MPC_DIR . 'includes/class-mpc-admin.php';
require MPC_DIR . 'includes/functions.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'myplan-consent', false, dirname( plugin_basename( MPC_FILE ) ) . '/languages' );

		MPC_Texts::init();
		MPC_Frontend::init();
		MPC_Blocker::init();
		MPC_Log::init();

		if ( is_admin() ) {
			MPC_Admin::init();
		}
	}
);

register_activation_hook( MPC_FILE, array( 'MPC_Log', 'install' ) );
register_deactivation_hook( MPC_FILE, array( 'MPC_Log', 'unschedule' ) );

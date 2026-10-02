<?php
/**
 * Settings storage: one option array, defaults and sanitising.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings access.
 */
class MPC_Settings {

	const OPTION = 'myplan_consent_settings';

	/**
	 * Optional categories, in display order. "necessary" is always on and is
	 * not listed here.
	 */
	const CATEGORIES = array( 'functional', 'analytics', 'marketing' );

	/**
	 * Cached settings for the request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'            => 1,
			// Google Consent Mode: "advanced" loads Google tags at once with
			// everything denied (cookieless pings); "basic" holds them back
			// until the visitor consents.
			'mode'               => 'advanced',
			'gtm_id'             => '',
			'ga4_id'             => '',
			'url_passthrough'    => 0,
			'ads_data_redaction' => 1,
			'wait_for_update'    => 500,

			'cat_functional'     => 1,
			'cat_analytics'      => 1,
			'cat_marketing'      => 1,
			'cookies_necessary'  => '',
			'cookies_functional' => '',
			// Starting points only; the site's real list goes in the settings.
			'cookies_analytics'  => '_ga | Google Analytics | ' . __( 'Distinguishes visitors', 'myplan-consent' ) . ' | ' . __( '2 years', 'myplan-consent' )
				. "\n_ga_* | Google Analytics | " . __( 'Keeps the session state', 'myplan-consent' ) . ' | ' . __( '2 years', 'myplan-consent' ),
			'cookies_marketing'  => '_gcl_* | Google Ads | ' . __( 'Measures ad conversions', 'myplan-consent' ) . ' | ' . __( '90 days', 'myplan-consent' )
				. "\n_fbp | Meta | " . __( 'Measures ad performance', 'myplan-consent' ) . ' | ' . __( '90 days', 'myplan-consent' ),

			'script_handles'     => '',
			'embed_rules'        => "youtube-nocookie.com = functional | YouTube\nyoutube.com = marketing | YouTube\nyoutu.be = marketing | YouTube\nplayer.vimeo.com = functional | Vimeo\ngoogle.com/maps = functional | Google Maps\nmaps.google. = functional | Google Maps\nopen.spotify.com = functional | Spotify\nfacebook.com/plugins = marketing | Facebook",

			'address'            => 'formal',
			'position'           => 'bar',
			'floating_button'    => 1,
			// Global Privacy Control: a browser-level refusal is taken as
			// "reject all" without showing the banner.
			'respect_gpc'        => 1,
			'color_accent'       => '#1f6feb',
			'color_background'   => '#ffffff',
			'color_text'         => '#1f2328',
			'radius'             => 8,

			'text_banner_title'  => '',
			'text_banner_body'   => '',
			'text_prefs_intro'   => '',

			'policy_page'        => 0,
			'privacy_page'       => 0,

			'expiry_days'        => 180,
			'version'            => 1,
			'log_enabled'        => 1,
			'log_retention_days' => 1095,
		);
	}

	/**
	 * All settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Drops the per-request cache after a save.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Enabled optional categories.
	 *
	 * @return string[]
	 */
	public static function enabled_categories() {
		return array_values(
			array_filter(
				self::CATEGORIES,
				static function ( $cat ) {
					return (bool) self::get( 'cat_' . $cat );
				}
			)
		);
	}

	/**
	 * Parses a "name | provider | purpose | expiry" list.
	 *
	 * @param string $category Category key.
	 * @return array<int, array{name: string, provider: string, purpose: string, expiry: string}>
	 */
	public static function cookie_rows( $category ) {
		$rows = array();

		foreach ( preg_split( '/\R/', (string) self::get( 'cookies_' . $category ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( '' === $parts[0] ) {
				continue;
			}

			$rows[] = array(
				'name'     => $parts[0],
				'provider' => isset( $parts[1] ) ? $parts[1] : '',
				'purpose'  => isset( $parts[2] ) ? $parts[2] : '',
				'expiry'   => isset( $parts[3] ) ? $parts[3] : '',
			);
		}

		return $rows;
	}

	/**
	 * Parses the embed rules: "host fragment = category | Provider".
	 *
	 * @return array<int, array{match: string, category: string, provider: string}>
	 */
	public static function embed_rules() {
		$rules = array();

		foreach ( preg_split( '/\R/', (string) self::get( 'embed_rules' ) ) as $line ) {
			if ( ! preg_match( '/^\s*([^=\s][^=]*?)\s*=\s*([a-z]+)\s*(?:\|\s*(.+?))?\s*$/', $line, $m ) ) {
				continue;
			}

			$rules[] = array(
				'match'    => $m[1],
				'category' => $m[2],
				'provider' => isset( $m[3] ) ? $m[3] : '',
			);
		}

		return $rules;
	}

	/**
	 * Parses the script handle map: "handle = category".
	 *
	 * @return array<string, string>
	 */
	public static function script_handles() {
		$map = array();

		foreach ( preg_split( '/\R/', (string) self::get( 'script_handles' ) ) as $line ) {
			if ( preg_match( '/^\s*([\w.-]+)\s*=\s*([a-z,\s]+?)\s*$/', $line, $m ) ) {
				$map[ $m[1] ] = preg_replace( '/\s+/', '', $m[2] );
			}
		}

		return $map;
	}

	/**
	 * Sanitises the submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$old      = self::all();
		$out      = array();

		foreach ( array( 'enabled', 'url_passthrough', 'ads_data_redaction', 'cat_functional', 'cat_analytics', 'cat_marketing', 'floating_button', 'respect_gpc', 'log_enabled' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['mode']     = ( isset( $input['mode'] ) && 'basic' === $input['mode'] ) ? 'basic' : 'advanced';
		$out['address']  = ( isset( $input['address'] ) && 'informal' === $input['address'] ) ? 'informal' : 'formal';
		$out['position'] = ( isset( $input['position'] ) && in_array( $input['position'], array( 'bar', 'box' ), true ) ) ? $input['position'] : 'bar';

		$gtm           = isset( $input['gtm_id'] ) ? strtoupper( trim( $input['gtm_id'] ) ) : '';
		$out['gtm_id'] = preg_match( '/^GTM-[A-Z0-9]+$/', $gtm ) ? $gtm : '';
		$ga4           = isset( $input['ga4_id'] ) ? strtoupper( trim( $input['ga4_id'] ) ) : '';
		$out['ga4_id'] = preg_match( '/^G-[A-Z0-9]+$/', $ga4 ) ? $ga4 : '';

		$out['wait_for_update']    = isset( $input['wait_for_update'] ) ? max( 0, min( 5000, (int) $input['wait_for_update'] ) ) : $defaults['wait_for_update'];
		$out['radius']             = isset( $input['radius'] ) ? max( 0, min( 32, (int) $input['radius'] ) ) : $defaults['radius'];
		$out['expiry_days']        = isset( $input['expiry_days'] ) ? max( 1, min( 395, (int) $input['expiry_days'] ) ) : $defaults['expiry_days'];
		$out['log_retention_days'] = isset( $input['log_retention_days'] ) ? max( 30, min( 3650, (int) $input['log_retention_days'] ) ) : $defaults['log_retention_days'];
		$out['policy_page']        = isset( $input['policy_page'] ) ? absint( $input['policy_page'] ) : 0;
		$out['privacy_page']       = isset( $input['privacy_page'] ) ? absint( $input['privacy_page'] ) : 0;

		foreach ( array( 'color_accent', 'color_background', 'color_text' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}

		foreach ( array( 'cookies_necessary', 'cookies_functional', 'cookies_analytics', 'cookies_marketing', 'script_handles', 'embed_rules' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
		}

		$out['text_banner_title'] = isset( $input['text_banner_title'] ) ? sanitize_text_field( $input['text_banner_title'] ) : '';
		$out['text_banner_body']  = isset( $input['text_banner_body'] ) ? wp_kses_post( $input['text_banner_body'] ) : '';
		$out['text_prefs_intro']  = isset( $input['text_prefs_intro'] ) ? wp_kses_post( $input['text_prefs_intro'] ) : '';

		// Asking again invalidates every stored choice: the version is part of
		// the consent cookie, and a mismatch shows the banner.
		// WordPress runs the sanitiser twice when the option is first created,
		// and the second pass gets the already sanitised array (no "reask").
		$out['version'] = isset( $input['version'] ) && ! isset( $input['reask'] ) ? max( (int) $input['version'], (int) $old['version'] ) : (int) $old['version'] + ( empty( $input['reask'] ) ? 0 : 1 );

		self::flush();

		return $out;
	}
}

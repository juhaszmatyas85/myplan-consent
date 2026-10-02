<?php
/**
 * Visitor-facing texts.
 *
 * Every default string goes through gettext, so the banner follows the site
 * language under WPML and Polylang, which switch the locale. Strings that
 * differ between formal and informal address carry the context "formal" or
 * "informal": English has one wording, Hungarian (and others) two.
 *
 * TranslatePress does not switch the locale; it rewrites the rendered HTML.
 * For it the plugin loads its own translation for the TranslatePress language
 * and marks the gettext-made elements data-no-translation, so the machine
 * translator does not translate them a second time. Texts typed into the
 * settings are left to the multilingual plugin: WPML and Polylang get them as
 * registered strings, TranslatePress translates them in the page.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Texts.
 */
class MPC_Texts {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_strings' ) );
	}

	/**
	 * Registers the free-text settings with Polylang. WPML picks them up from
	 * wpml-config.xml.
	 */
	public static function register_strings() {
		if ( ! function_exists( 'pll_register_string' ) ) {
			return;
		}

		foreach ( array( 'text_banner_title', 'text_banner_body', 'text_prefs_intro' ) as $key ) {
			$value = (string) MPC_Settings::get( $key );

			if ( '' !== $value ) {
				pll_register_string( $key, $value, 'MyPlan Cookie Consent', 'text_banner_title' !== $key );
			}
		}
	}

	/**
	 * Whether TranslatePress is rendering this request.
	 *
	 * @return bool
	 */
	public static function is_translatepress() {
		return class_exists( 'TRP_Translate_Press' );
	}

	/**
	 * Loads the plugin translation for the language TranslatePress is showing.
	 *
	 * Called right before rendering, once TranslatePress has decided the
	 * language. A locale without a translation file falls back to the English
	 * source strings.
	 */
	public static function switch_to_visitor_language() {
		global $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName

		if ( ! self::is_translatepress() || empty( $TRP_LANGUAGE ) || determine_locale() === $TRP_LANGUAGE ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			return;
		}

		unload_textdomain( 'myplan-consent' );

		$file = MPC_DIR . 'languages/myplan-consent-' . $TRP_LANGUAGE . '.mo'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName

		if ( file_exists( $file ) ) {
			load_textdomain( 'myplan-consent', $file );
		}
	}

	/**
	 * Attribute that keeps TranslatePress off a gettext-made string.
	 *
	 * @param bool $from_settings Whether the text was typed into the settings.
	 * @return string
	 */
	public static function no_translate_attr( $from_settings = false ) {
		return ( self::is_translatepress() && ! $from_settings ) ? ' data-no-translation=""' : '';
	}

	/**
	 * Whether the site addresses the visitor informally.
	 *
	 * @return bool
	 */
	public static function informal() {
		return 'informal' === MPC_Settings::get( 'address' );
	}

	/**
	 * A text typed into the settings, through the multilingual plugin.
	 *
	 * @param string $key Setting key.
	 * @return string Empty when not set.
	 */
	public static function custom( $key ) {
		$value = (string) MPC_Settings::get( $key );

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'pll__' ) ) {
			return pll__( $value );
		}

		return (string) apply_filters( 'wpml_translate_single_string', $value, 'MyPlan Cookie Consent', $key );
	}

	/**
	 * Banner and dialog texts.
	 *
	 * @return array<string, string>
	 */
	public static function strings() {
		$informal = self::informal();

		return array(
			'banner_title'   => __( 'Cookies on this website', 'myplan-consent' ),
			'banner_body'    => $informal
				? _x( 'We use cookies that are necessary for this website to work. With your consent, we also use optional cookies and third-party services. You can change your decision at any time.', 'informal', 'myplan-consent' )
				: _x( 'We use cookies that are necessary for this website to work. With your consent, we also use optional cookies and third-party services. You can change your decision at any time.', 'formal', 'myplan-consent' ),
			'prefs_title'    => __( 'Cookie settings', 'myplan-consent' ),
			'prefs_intro'    => $informal
				? _x( 'Choose which categories of cookies you allow. Strictly necessary cookies are always active, because the website cannot work without them. You can change your decision at any time with the cookie settings button or link.', 'informal', 'myplan-consent' )
				: _x( 'Choose which categories of cookies you allow. Strictly necessary cookies are always active, because the website cannot work without them. You can change your decision at any time with the cookie settings button or link.', 'formal', 'myplan-consent' ),
			'accept_all'     => __( 'Accept all', 'myplan-consent' ),
			'reject_all'     => __( 'Reject all', 'myplan-consent' ),
			'settings'       => __( 'Settings', 'myplan-consent' ),
			'save'           => __( 'Save my choices', 'myplan-consent' ),
			'close'          => __( 'Close', 'myplan-consent' ),
			'always_on'      => __( 'Always active', 'myplan-consent' ),
			'cookies_used'   => __( 'Cookies and services', 'myplan-consent' ),
			// Under the list of an optional category: the list says what the
			// category would use, not what is running now.
			'only_if_on'     => $informal
				? _x( 'Used only if you allow this category.', 'informal', 'myplan-consent' )
				: _x( 'Used only if you allow this category.', 'formal', 'myplan-consent' ),
			'col_name'       => __( 'Name', 'myplan-consent' ),
			'col_provider'   => __( 'Provider', 'myplan-consent' ),
			'col_purpose'    => __( 'Purpose', 'myplan-consent' ),
			'col_expiry'     => __( 'Expiry', 'myplan-consent' ),
			'policy_link'    => __( 'Cookie policy', 'myplan-consent' ),
			'privacy_link'   => __( 'Privacy notice', 'myplan-consent' ),
			'floating_label' => __( 'Cookie settings', 'myplan-consent' ),
			'embed_allow'    => __( 'Allow and show', 'myplan-consent' ),
			'own_cookie'     => $informal
				? _x( 'Stores your cookie choices', 'informal', 'myplan-consent' )
				: _x( 'Stores your cookie choices', 'formal', 'myplan-consent' ),
		);
	}

	/**
	 * Category names and descriptions.
	 *
	 * @return array<string, array{name: string, desc: string}>
	 */
	public static function categories() {
		$informal = self::informal();

		return array(
			'necessary'  => array(
				'name' => __( 'Strictly necessary', 'myplan-consent' ),
				'desc' => $informal
					? _x( 'Essential for the website to work, for example to remember your cookie choices or keep you signed in. They cannot be switched off.', 'informal', 'myplan-consent' )
					: _x( 'Essential for the website to work, for example to remember your cookie choices or keep you signed in. They cannot be switched off.', 'formal', 'myplan-consent' ),
			),
			'functional' => array(
				'name' => __( 'Functional', 'myplan-consent' ),
				'desc' => $informal
					? _x( 'Enable extra features and embedded content from other providers, such as maps and videos, and remember your preferences.', 'informal', 'myplan-consent' )
					: _x( 'Enable extra features and embedded content from other providers, such as maps and videos, and remember your preferences.', 'formal', 'myplan-consent' ),
			),
			'analytics'  => array(
				'name' => __( 'Analytics', 'myplan-consent' ),
				'desc' => __( 'Help us understand how visitors use the website, so we can improve it. The information is collected in aggregate.', 'myplan-consent' ),
			),
			'marketing'  => array(
				'name' => __( 'Marketing', 'myplan-consent' ),
				'desc' => $informal
					? _x( 'Used to measure the effectiveness of our advertising and to show you relevant ads on other websites.', 'informal', 'myplan-consent' )
					: _x( 'Used to measure the effectiveness of our advertising and to show you relevant ads on other websites.', 'formal', 'myplan-consent' ),
			),
		);
	}

	/**
	 * Placeholder text for a blocked embed.
	 *
	 * @param string $provider Provider name.
	 * @param string $category Category name, already translated.
	 * @return string
	 */
	public static function embed_notice( $provider, $category ) {
		$format = self::informal()
			/* translators: 1: provider, for example YouTube. 2: cookie category name. */
			? _x( 'This content is provided by %1$s and may use cookies. To view it, allow the “%2$s” cookies.', 'informal', 'myplan-consent' )
			/* translators: 1: provider, for example YouTube. 2: cookie category name. */
			: _x( 'This content is provided by %1$s and may use cookies. To view it, allow the “%2$s” cookies.', 'formal', 'myplan-consent' );

		return sprintf( $format, $provider, $category );
	}

	/**
	 * Expiry of the consent cookie, for the cookie table.
	 *
	 * @param int $days Days.
	 * @return string
	 */
	public static function days( $days ) {
		/* translators: %d: number of days. */
		return sprintf( _n( '%d day', '%d days', $days, 'myplan-consent' ), $days );
	}
}

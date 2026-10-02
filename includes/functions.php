<?php
/**
 * Template helpers for themes and other plugins.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mpc_embed' ) ) {
	/**
	 * Wraps an iframe in the consent placeholder.
	 *
	 * Use it for embeds the content filters do not see, for example a map a
	 * theme prints itself. Without a category the embed rules decide; an iframe
	 * no rule matches is returned unchanged.
	 *
	 * @param string $iframe   iframe markup.
	 * @param string $category Optional category key.
	 * @param string $provider Optional provider name for the notice.
	 * @return string
	 */
	function mpc_embed( $iframe, $category = '', $provider = '' ) {
		if ( ! class_exists( 'MPC_Blocker' ) || ! MPC_Settings::get( 'enabled' ) ) {
			return $iframe;
		}

		if ( '' === $category ) {
			return MPC_Blocker::block_iframes( $iframe );
		}

		return MPC_Blocker::wrap( $iframe, $category, $provider );
	}
}

if ( ! function_exists( 'mpc_settings_link' ) ) {
	/**
	 * A link that reopens the cookie settings.
	 *
	 * @param string $label Optional label.
	 * @return string
	 */
	function mpc_settings_link( $label = '' ) {
		return class_exists( 'MPC_Frontend' ) ? MPC_Frontend::shortcode_link( array( 'label' => $label ) ) : '';
	}
}

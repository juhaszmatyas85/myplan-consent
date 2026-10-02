<?php
/**
 * Holds back scripts and embeds until their category is allowed.
 *
 * Scripts: an enqueued handle listed in the settings is printed as
 * type="text/plain" with data-mpc-consent; the front-end script swaps it for a
 * real one once the category is allowed. Any theme or snippet can opt in the
 * same way by hand.
 *
 * Embeds: iframes whose address matches an embed rule lose their src (kept in
 * data-mpc-src) and get a placeholder with an "allow and show" button.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blocker.
 */
class MPC_Blocker {

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! MPC_Settings::get( 'enabled' ) || is_admin() ) {
			return;
		}

		add_filter( 'script_loader_tag', array( __CLASS__, 'script_tag' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'block_iframes' ), 99 );
		add_filter( 'embed_oembed_html', array( __CLASS__, 'block_iframes' ), 99 );
		add_filter( 'widget_text', array( __CLASS__, 'block_iframes' ), 99 );
		add_filter( 'widget_block_content', array( __CLASS__, 'block_iframes' ), 99 );
	}

	/**
	 * Turns a listed script handle into an inert script.
	 *
	 * @param string $tag    Script tag(s).
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function script_tag( $tag, $handle ) {
		$map = MPC_Settings::script_handles();

		if ( ! isset( $map[ $handle ] ) ) {
			return $tag;
		}

		$category = $map[ $handle ];

		return preg_replace_callback(
			'/<script\b([^>]*)>/i',
			static function ( $m ) use ( $category ) {
				$attrs = preg_replace( '/\stype=("|\')[^"\']*\1/i', '', $m[1] );

				return '<script type="text/plain" data-mpc-consent="' . esc_attr( $category ) . '"' . $attrs . '>';
			},
			$tag
		);
	}

	/**
	 * The rule an address falls under.
	 *
	 * @param string $src iframe address.
	 * @return array|null
	 */
	private static function rule_for( $src ) {
		$enabled = MPC_Settings::enabled_categories();

		foreach ( MPC_Settings::embed_rules() as $rule ) {
			if ( false !== stripos( $src, $rule['match'] ) ) {
				// A rule pointing at a category the site does not use is
				// switched off rather than blocking the embed for good.
				return in_array( $rule['category'], $enabled, true ) ? $rule : null;
			}
		}

		return null;
	}

	/**
	 * Wraps matching iframes in a placeholder.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function block_iframes( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<iframe' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<iframe\b[^>]*>.*?<\/iframe>/is',
			static function ( $m ) {
				if ( false !== strpos( $m[0], 'data-mpc-src' ) || ! preg_match( '/\ssrc=("|\')([^"\']+)\1/i', $m[0], $src ) ) {
					return $m[0];
				}

				$rule = self::rule_for( html_entity_decode( $src[2] ) );

				return $rule ? self::wrap( $m[0], $rule['category'], $rule['provider'] ) : $m[0];
			},
			$html
		);
	}

	/**
	 * Wraps one iframe.
	 *
	 * @param string $iframe   iframe markup.
	 * @param string $category Category key.
	 * @param string $provider Provider name; the host when empty.
	 * @return string
	 */
	public static function wrap( $iframe, $category, $provider = '' ) {
		if ( ! preg_match( '/\ssrc=("|\')([^"\']+)\1/i', $iframe, $src ) ) {
			return $iframe;
		}

		if ( '' === $provider ) {
			$provider = (string) wp_parse_url( html_entity_decode( $src[2] ), PHP_URL_HOST );
		}

		$cats   = MPC_Texts::categories();
		$name   = isset( $cats[ $category ] ) ? $cats[ $category ]['name'] : $category;
		$t      = MPC_Texts::strings();
		$nt     = MPC_Texts::no_translate_attr();
		$inert  = str_replace( $src[0], ' data-mpc-src=' . $src[1] . $src[2] . $src[1], $iframe );
		$width  = preg_match( '/\swidth=("|\')?(\d+)/i', $iframe, $w ) ? (int) $w[2] : 0;
		$height = preg_match( '/\sheight=("|\')?(\d+)/i', $iframe, $h ) ? (int) $h[2] : 0;
		$ratio  = ( $width && $height ) ? sprintf( ' style="--mpc-ratio:%d/%d"', $width, $height ) : '';

		return sprintf(
			'<div class="mpc-embed" data-mpc-embed data-mpc-consent="%1$s"%2$s><div class="mpc-embed__placeholder"><p%3$s>%4$s</p><p class="mpc-embed__actions"><button type="button" class="mpc-btn" data-mpc-allow="%1$s"%3$s>%5$s</button> <button type="button" class="mpc-link" data-mpc-open%3$s>%6$s</button></p></div>%7$s</div>',
			esc_attr( $category ),
			$ratio,
			$nt,
			esc_html( MPC_Texts::embed_notice( $provider, $name ) ),
			esc_html( $t['embed_allow'] ),
			esc_html( $t['floating_label'] ),
			$inert
		);
	}
}

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
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'elementor_video' ), 99, 2 );
	}

	/**
	 * Holds back Elementor's Video widget (YouTube, Vimeo).
	 *
	 * The widget has no iframe in its markup: Elementor's front-end handler
	 * reads the address from the wrapper's data-settings and loads the YouTube
	 * iframe API (and with it YouTube's cookies) on page load, so the content
	 * filters never see anything to block. Here the widget's own markup is
	 * replaced by a plain embed iframe that goes through the embed rules like
	 * any other, and the video type is switched to "hosted" before Elementor
	 * prints data-settings, which makes the handler step aside.
	 *
	 * YouTube is embedded from youtube-nocookie.com and Vimeo with dnt=1, the
	 * privacy-enhanced modes of both.
	 *
	 * @param string $content Widget markup.
	 * @param object $widget  Elementor widget.
	 * @return string
	 */
	public static function elementor_video( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'video' !== $widget->get_name() ) {
			return $content;
		}

		if ( class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $content;
		}

		$s    = $widget->get_settings_for_display();
		$type = isset( $s['video_type'] ) ? $s['video_type'] : 'youtube';
		$src  = '';

		if ( 'youtube' === $type && ! empty( $s['youtube_url'] )
			&& preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:(?:watch)?\?(?:.*&)?vi?=|(?:embed|v|vi|shorts|live)/))([A-Za-z0-9_-]{6,})~', $s['youtube_url'], $m ) ) {
			$src = 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
		} elseif ( 'vimeo' === $type && ! empty( $s['vimeo_url'] ) && preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $s['vimeo_url'], $m ) ) {
			$src = 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		}

		if ( '' === $src || ! self::rule_for( $src ) ) {
			return $content;
		}

		// Elementor caches the parsed settings it builds data-settings from,
		// so the cache is dropped after the change (Elementor 3.30+; on older
		// versions the handler still runs, but the embed itself stays held back).
		// ⚠️ With Elementor's element cache on, rendered widgets are stored in
		// the _elementor_element_cache post meta: clear Elementor's cache
		// (Elementor → Tools → Clear Files & Data) after activating this plugin.
		$widget->set_settings( 'video_type', 'hosted' );
		if ( method_exists( $widget, 'reset_render_state' ) ) {
			$widget->reset_render_state();
		}

		$iframe = sprintf(
			'<iframe src="%s" width="640" height="360" title="%s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="width:100%%;height:100%%;border:0"></iframe>',
			esc_url( $src ),
			esc_attr__( 'Video', 'myplan-consent' )
		);

		return '<div class="elementor-wrapper elementor-open-inline mpc-elementor-video">' . self::block_iframes( $iframe ) . '</div>';
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

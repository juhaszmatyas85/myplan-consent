<?php
/**
 * Front end: the consent defaults in the head, the Google tags, the banner and
 * the preferences dialog, and the shortcodes.
 *
 * The banner is rendered on the server (hidden) rather than built by the
 * script, so page caches can serve one copy to everyone and multilingual
 * plugins see real HTML. The script only decides whether to show it.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front end.
 */
class MPC_Frontend {

	const COOKIE = 'mp_consent';

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( ! MPC_Settings::get( 'enabled' ) ) {
			return;
		}

		// Before everything else in the head: the consent defaults have to be
		// in the dataLayer before any Google tag, whichever plugin prints it.
		add_action( 'wp_head', array( __CLASS__, 'head' ), PHP_INT_MIN );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 5 );

		add_shortcode( 'mpc_settings_link', array( __CLASS__, 'shortcode_link' ) );
		add_shortcode( 'mpc_cookie_table', array( __CLASS__, 'shortcode_table' ) );

		// WP Consent API: tells plugins that read consent through it that a
		// consent manager is present and that this is an opt-in regime.
		add_filter( 'wp_consent_api_registered_' . plugin_basename( MPC_FILE ), '__return_true' );
		add_filter(
			'wp_get_consent_type',
			static function () {
				return 'optin';
			}
		);
	}

	/**
	 * Whether the banner should run on this request.
	 *
	 * @return bool
	 */
	private static function active() {
		return ! is_admin() && ! wp_doing_ajax() && ! is_customize_preview() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! is_feed() && ! is_embed();
	}

	/**
	 * Consent defaults, then the Google tags.
	 */
	public static function head() {
		if ( ! self::active() ) {
			return;
		}

		MPC_Texts::switch_to_visitor_language();
		MPC_Settings::flush();

		$config = array(
			'name'        => self::COOKIE,
			'version'     => (int) MPC_Settings::get( 'version' ),
			'days'        => (int) MPC_Settings::get( 'expiry_days' ),
			'wait'        => (int) MPC_Settings::get( 'wait_for_update' ),
			'redaction'   => (bool) MPC_Settings::get( 'ads_data_redaction' ),
			'passthrough' => (bool) MPC_Settings::get( 'url_passthrough' ),
		);

		$script = <<<'JS'
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
(function(w,d,c){
function map(s){function g(k){return s&&s[k]?'granted':'denied';}return{ad_storage:g('marketing'),ad_user_data:g('marketing'),ad_personalization:g('marketing'),analytics_storage:g('analytics'),functionality_storage:g('functional'),personalization_storage:g('functional'),security_storage:'granted'};}
w.mpcConsentMap=map;
var st=null;try{var m=d.cookie.match(new RegExp('(?:^|; )'+c.name+'=([^;]*)'));if(m){st=JSON.parse(decodeURIComponent(m[1]));if(!st||st.v!==c.version||!st.ts||Date.now()/1e3-st.ts>c.days*86400){st=null;}}}catch(e){st=null;}
w.mpcStored=st;
var def=map(null);if(c.wait){def.wait_for_update=c.wait;}
gtag('consent','default',def);
if(c.redaction){gtag('set','ads_data_redaction',true);}
if(c.passthrough){gtag('set','url_passthrough',true);}
if(st){gtag('consent','update',map(st.c));}
})(window,document,%s);
JS;

		printf( "<script id=\"mpc-consent-default\">%s</script>\n", sprintf( $script, wp_json_encode( $config ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static script with JSON-encoded config.

		self::google_tags();
	}

	/**
	 * GTM and/or GA4, when configured. In basic mode they wait for consent.
	 */
	private static function google_tags() {
		$basic = 'basic' === MPC_Settings::get( 'mode' );
		$gtm   = (string) MPC_Settings::get( 'gtm_id' );
		$ga4   = (string) MPC_Settings::get( 'ga4_id' );

		if ( $gtm ) {
			$attrs = $basic ? ' type="text/plain" data-mpc-consent="analytics,marketing"' : '';
			printf(
				"<script%s>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',%s);</script>\n",
				$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute string.
				wp_json_encode( $gtm )
			);
		}

		if ( $ga4 ) {
			$attrs = $basic ? ' type="text/plain" data-mpc-consent="analytics"' : '';
			printf(
				"<script async src=\"%s\"%s></script>\n<script%s>gtag('js',new Date());gtag('config',%s);</script>\n",
				esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . $ga4 ),
				$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute string.
				$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute string.
				wp_json_encode( $ga4 )
			);
		}
	}

	/**
	 * Stylesheet, script and their config.
	 */
	public static function assets() {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_style( 'myplan-consent', MPC_URL . 'assets/consent.css', array(), MPC_VERSION . '.' . filemtime( MPC_DIR . 'assets/consent.css' ) );
		wp_add_inline_style(
			'myplan-consent',
			sprintf(
				':root{--mpc-accent:%s;--mpc-bg:%s;--mpc-text:%s;--mpc-radius:%dpx;}',
				MPC_Settings::get( 'color_accent' ),
				MPC_Settings::get( 'color_background' ),
				MPC_Settings::get( 'color_text' ),
				(int) MPC_Settings::get( 'radius' )
			)
		);

		wp_enqueue_script(
			'myplan-consent',
			MPC_URL . 'assets/consent.js',
			array(),
			MPC_VERSION . '.' . filemtime( MPC_DIR . 'assets/consent.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'cookieName' => self::COOKIE,
			'version'    => (int) MPC_Settings::get( 'version' ),
			'expiryDays' => (int) MPC_Settings::get( 'expiry_days' ),
			'categories' => MPC_Settings::enabled_categories(),
			'floating'   => (bool) MPC_Settings::get( 'floating_button' ),
			'respectGpc' => (bool) MPC_Settings::get( 'respect_gpc' ),
			'logUrl'     => MPC_Settings::get( 'log_enabled' ) ? esc_url_raw( MPC_Log::endpoint() ) : '',
			// Cookies to clear when a category is withdrawn. Prefix match when
			// the name ends in an asterisk.
			'cleanup'    => array(
				'functional' => self::cookie_names( 'functional' ),
				'analytics'  => array_values( array_unique( array_merge( array( '_ga', '_ga_*', '_gid', '_gat*' ), self::cookie_names( 'analytics' ) ) ) ),
				'marketing'  => array_values( array_unique( array_merge( array( '_gcl_*', '_fbp', '_fbc' ), self::cookie_names( 'marketing' ) ) ) ),
			),
		);

		wp_add_inline_script( 'myplan-consent', 'window.mpcConfig=' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Cookie names listed for a category.
	 *
	 * @param string $category Category key.
	 * @return string[]
	 */
	private static function cookie_names( $category ) {
		return wp_list_pluck( MPC_Settings::cookie_rows( $category ), 'name' );
	}

	/**
	 * Policy and privacy links for the banner and dialog.
	 *
	 * @param array $t Strings.
	 * @return string
	 */
	private static function links( $t ) {
		$links = array();

		foreach ( array(
			'policy_page'  => 'policy_link',
			'privacy_page' => 'privacy_link',
		) as $setting => $label ) {
			$page = (int) MPC_Settings::get( $setting );

			if ( $page && 'publish' === get_post_status( $page ) ) {
				$links[] = sprintf( '<a href="%s"%s>%s</a>', esc_url( get_permalink( $page ) ), MPC_Texts::no_translate_attr(), esc_html( $t[ $label ] ) );
			}
		}

		return $links ? '<span class="mpc-links">' . implode( '<span aria-hidden="true"> · </span>', $links ) . '</span>' : '';
	}

	/**
	 * The cookie list of one category, as a table.
	 *
	 * @param string $category Category key.
	 * @param array  $t        Strings.
	 * @return string
	 */
	private static function cookie_table( $category, $t ) {
		$rows = MPC_Settings::cookie_rows( $category );

		if ( 'necessary' === $category ) {
			array_unshift(
				$rows,
				array(
					'name'     => self::COOKIE,
					'provider' => wp_parse_url( home_url(), PHP_URL_HOST ),
					'purpose'  => $t['own_cookie'],
					'expiry'   => MPC_Texts::days( (int) MPC_Settings::get( 'expiry_days' ) ),
				)
			);
		}

		if ( ! $rows ) {
			return '';
		}

		$nt   = MPC_Texts::no_translate_attr();
		$html = '<details class="mpc-cookies"><summary' . $nt . '>' . esc_html( $t['cookies_used'] ) . '</summary><div class="mpc-cookies__scroll"><table><thead><tr>';

		foreach ( array( 'col_name', 'col_provider', 'col_purpose', 'col_expiry' ) as $col ) {
			$html .= '<th scope="col"' . $nt . '>' . esc_html( $t[ $col ] ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$html .= sprintf(
				'<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $row['name'] ),
				esc_html( $row['provider'] ),
				esc_html( $row['purpose'] ),
				esc_html( $row['expiry'] )
			);
		}

		$html .= '</tbody></table></div>';

		if ( 'necessary' !== $category ) {
			$html .= '<p class="mpc-cookies__note"' . $nt . '>' . esc_html( $t['only_if_on'] ) . '</p>';
		}

		return $html . '</details>';
	}

	/**
	 * Banner, preferences dialog and the floating button.
	 */
	public static function render() {
		if ( ! self::active() ) {
			return;
		}

		$t          = MPC_Texts::strings();
		$cats       = MPC_Texts::categories();
		$nt         = MPC_Texts::no_translate_attr();
		$title      = MPC_Texts::custom( 'text_banner_title' );
		$body       = MPC_Texts::custom( 'text_banner_body' );
		$intro      = MPC_Texts::custom( 'text_prefs_intro' );
		$title_attr = MPC_Texts::no_translate_attr( '' !== $title );
		$body_attr  = MPC_Texts::no_translate_attr( '' !== $body );
		$intro_attr = MPC_Texts::no_translate_attr( '' !== $intro );
		$position   = 'box' === MPC_Settings::get( 'position' ) ? 'box' : 'bar';
		$links      = self::links( $t );
		?>
<div class="mpc" id="mpc" data-mpc-root>
	<section class="mpc-banner mpc-banner--<?php echo esc_attr( $position ); ?>" tabindex="-1" aria-labelledby="mpc-banner-title" aria-describedby="mpc-banner-body" data-mpc-banner hidden>
		<div class="mpc-banner__inner">
			<div class="mpc-banner__text">
				<h2 class="mpc-title" id="mpc-banner-title"<?php echo $title_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( '' !== $title ? $title : $t['banner_title'] ); ?></h2>
				<div class="mpc-body" id="mpc-banner-body">
					<p<?php echo $body_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo '' !== $body ? wp_kses_post( $body ) : esc_html( $t['banner_body'] ); ?></p>
					<?php echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
				</div>
			</div>
			<div class="mpc-actions">
				<button type="button" class="mpc-btn mpc-btn--ghost" data-mpc-action="settings"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['settings'] ); ?></button>
				<button type="button" class="mpc-btn" data-mpc-action="reject"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['reject_all'] ); ?></button>
				<button type="button" class="mpc-btn" data-mpc-action="accept"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['accept_all'] ); ?></button>
			</div>
		</div>
	</section>

	<dialog class="mpc-modal" aria-labelledby="mpc-modal-title" data-mpc-modal>
		<div class="mpc-modal__inner">
			<header class="mpc-modal__head">
				<h2 class="mpc-title" id="mpc-modal-title"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['prefs_title'] ); ?></h2>
				<button type="button" class="mpc-close" data-mpc-action="close" aria-label="<?php echo esc_attr( $t['close'] ); ?>"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>&times;</button>
			</header>

			<div class="mpc-modal__body">
				<div class="mpc-body">
					<p<?php echo $intro_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo '' !== $intro ? wp_kses_post( $intro ) : esc_html( $t['prefs_intro'] ); ?></p>
					<?php echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
				</div>

				<?php foreach ( array_merge( array( 'necessary' ), MPC_Settings::enabled_categories() ) as $cat ) : ?>
					<div class="mpc-cat">
						<div class="mpc-cat__head">
							<label class="mpc-cat__name" for="mpc-cat-<?php echo esc_attr( $cat ); ?>"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $cats[ $cat ]['name'] ); ?></label>
							<?php if ( 'necessary' === $cat ) : ?>
								<span class="mpc-cat__always"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['always_on'] ); ?></span>
								<input class="mpc-switch" type="checkbox" role="switch" id="mpc-cat-necessary" checked disabled>
							<?php else : ?>
								<input class="mpc-switch" type="checkbox" role="switch" id="mpc-cat-<?php echo esc_attr( $cat ); ?>" data-mpc-cat="<?php echo esc_attr( $cat ); ?>" aria-describedby="mpc-cat-desc-<?php echo esc_attr( $cat ); ?>">
							<?php endif; ?>
						</div>
						<p class="mpc-cat__desc" id="mpc-cat-desc-<?php echo esc_attr( $cat ); ?>"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $cats[ $cat ]['desc'] ); ?></p>
						<?php echo self::cookie_table( $cat, $t ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
					</div>
				<?php endforeach; ?>
			</div>

			<footer class="mpc-modal__foot">
				<button type="button" class="mpc-btn" data-mpc-action="reject"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['reject_all'] ); ?></button>
				<button type="button" class="mpc-btn mpc-btn--ghost" data-mpc-action="save"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['save'] ); ?></button>
				<button type="button" class="mpc-btn" data-mpc-action="accept"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $t['accept_all'] ); ?></button>
			</footer>
		</div>
	</dialog>

		<?php if ( MPC_Settings::get( 'floating_button' ) ) : ?>
	<button type="button" class="mpc-float" data-mpc-open aria-label="<?php echo esc_attr( $t['floating_label'] ); ?>" title="<?php echo esc_attr( $t['floating_label'] ); ?>"<?php echo $nt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> hidden>
		<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21.6 11.2a3 3 0 0 1-3.3-2.9 3 3 0 0 1-3.6-3.6A3 3 0 0 1 12.8 2 10 10 0 1 0 22 12.6a3 3 0 0 1-.4-1.4ZM8 9.5A1.5 1.5 0 1 1 8 12.5 1.5 1.5 0 0 1 8 9.5Zm2 7a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm5 .5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm1-5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg>
	</button>
		<?php endif; ?>
</div>
		<?php
	}

	/**
	 * [mpc_settings_link label="…"]: reopens the preferences dialog.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_link( $atts ) {
		$atts  = shortcode_atts( array( 'label' => '' ), $atts, 'mpc_settings_link' );
		$label = '' !== $atts['label'] ? $atts['label'] : MPC_Texts::strings()['floating_label'];

		return sprintf( '<a href="#cookie-settings" class="mpc-open" data-mpc-open>%s</a>', esc_html( $label ) );
	}

	/**
	 * [mpc_cookie_table]: the categories and their cookies, for the cookie
	 * policy page, from the same settings the dialog uses.
	 *
	 * @return string
	 */
	public static function shortcode_table() {
		$t    = MPC_Texts::strings();
		$cats = MPC_Texts::categories();
		$html = '<div class="mpc-policy-table">';

		foreach ( array_merge( array( 'necessary' ), MPC_Settings::enabled_categories() ) as $cat ) {
			$html .= '<h3>' . esc_html( $cats[ $cat ]['name'] ) . '</h3><p>' . esc_html( $cats[ $cat ]['desc'] ) . '</p>';
			$html .= str_replace( '<details class="mpc-cookies">', '<details class="mpc-cookies" open>', self::cookie_table( $cat, $t ) );
		}

		return $html . '</div>';
	}
}

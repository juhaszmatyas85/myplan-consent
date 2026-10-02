<?php
/**
 * Settings screen (Settings → Cookie consent) and the log export.
 *
 * @package myplan-consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class MPC_Admin {

	const PAGE = 'myplan-consent';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_mpc_export', array( __CLASS__, 'export' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MPC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_options_page(
			__( 'Cookie consent', 'myplan-consent' ),
			__( 'Cookie consent', 'myplan-consent' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ), esc_html__( 'Settings', 'myplan-consent' ) ) );

		return $links;
	}

	/**
	 * Sections and fields.
	 */
	public static function register() {
		register_setting(
			'myplan_consent',
			MPC_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'MPC_Settings', 'sanitize' ),
			)
		);

		$sections = array(
			'general'    => array(
				__( 'General', 'myplan-consent' ),
				array(
					'enabled'            => array( 'checkbox', __( 'Show the banner and apply consent', 'myplan-consent' ) ),
					'mode'               => array(
						'select',
						__( 'Google Consent Mode', 'myplan-consent' ),
						array(
							'advanced' => __( 'Advanced: Google tags load at once with consent denied (cookieless pings, modelled conversions)', 'myplan-consent' ),
							'basic'    => __( 'Basic: Google tags load only after consent', 'myplan-consent' ),
						),
					),
					'gtm_id'             => array( 'text', __( 'Google Tag Manager ID', 'myplan-consent' ), __( 'GTM-XXXXXXX. Leave empty if another plugin or the theme loads it; the consent defaults still come first.', 'myplan-consent' ) ),
					'ga4_id'             => array( 'text', __( 'Google Analytics 4 ID', 'myplan-consent' ), __( 'G-XXXXXXXXXX. Only if GA4 is not loaded through Tag Manager.', 'myplan-consent' ) ),
					'ads_data_redaction' => array( 'checkbox', __( 'Redact ad click identifiers while ad consent is denied (ads_data_redaction)', 'myplan-consent' ) ),
					'url_passthrough'    => array( 'checkbox', __( 'Pass ad click information through URLs while consent is denied (url_passthrough)', 'myplan-consent' ) ),
					'wait_for_update'    => array( 'number', __( 'wait_for_update (ms)', 'myplan-consent' ), __( 'How long Google tags wait for a stored choice before firing.', 'myplan-consent' ) ),
				),
			),
			'categories' => array(
				__( 'Categories and cookies', 'myplan-consent' ),
				array(
					'cat_functional'     => array( 'checkbox', __( 'Functional (embedded maps, videos, preferences)', 'myplan-consent' ) ),
					'cat_analytics'      => array( 'checkbox', __( 'Analytics', 'myplan-consent' ) ),
					'cat_marketing'      => array( 'checkbox', __( 'Marketing', 'myplan-consent' ) ),
					'cookies_necessary'  => array( 'textarea', __( 'Strictly necessary cookies', 'myplan-consent' ), __( 'One per line: name | provider | purpose | expiry. The consent cookie itself is always listed.', 'myplan-consent' ) ),
					'cookies_functional' => array( 'textarea', __( 'Functional cookies', 'myplan-consent' ) ),
					'cookies_analytics'  => array( 'textarea', __( 'Analytics cookies', 'myplan-consent' ) ),
					'cookies_marketing'  => array( 'textarea', __( 'Marketing cookies', 'myplan-consent' ), __( 'Listed cookies are also deleted when a visitor withdraws the category (a trailing * matches a prefix).', 'myplan-consent' ) ),
				),
			),
			'blocking'   => array(
				__( 'Blocking', 'myplan-consent' ),
				array(
					'script_handles' => array( 'textarea', __( 'Script handles', 'myplan-consent' ), __( 'One per line: handle = category. These enqueued scripts run only after consent. In your own markup use <script type="text/plain" data-mpc-consent="analytics">.', 'myplan-consent' ) ),
					'embed_rules'    => array( 'textarea', __( 'Embed rules', 'myplan-consent' ), __( 'One per line: address fragment = category | provider. Matching iframes in content get a placeholder until consent. Themes can use mpc_embed( $iframe ).', 'myplan-consent' ) ),
				),
			),
			'appearance' => array(
				__( 'Appearance and texts', 'myplan-consent' ),
				array(
					'address'           => array(
						'select',
						__( 'Form of address', 'myplan-consent' ),
						array(
							'formal'   => __( 'Formal', 'myplan-consent' ),
							'informal' => __( 'Informal', 'myplan-consent' ),
						),
					),
					'position'          => array(
						'select',
						__( 'Banner position', 'myplan-consent' ),
						array(
							'bar' => __( 'Bar along the bottom', 'myplan-consent' ),
							'box' => __( 'Box in the bottom left corner', 'myplan-consent' ),
						),
					),
					'floating_button'   => array( 'checkbox', __( 'Show a floating button to reopen the settings', 'myplan-consent' ), __( 'Without it, put [mpc_settings_link] or a link to #cookie-settings in the footer: withdrawing consent must be as easy as giving it.', 'myplan-consent' ) ),
					'color_accent'      => array( 'color', __( 'Accent colour', 'myplan-consent' ) ),
					'color_background'  => array( 'color', __( 'Background colour', 'myplan-consent' ) ),
					'color_text'        => array( 'color', __( 'Text colour', 'myplan-consent' ) ),
					'radius'            => array( 'number', __( 'Corner radius (px)', 'myplan-consent' ) ),
					'text_banner_title' => array( 'text', __( 'Banner title', 'myplan-consent' ), __( 'Leave the texts empty to use the built-in, translated wording.', 'myplan-consent' ) ),
					'text_banner_body'  => array( 'textarea', __( 'Banner text', 'myplan-consent' ) ),
					'text_prefs_intro'  => array( 'textarea', __( 'Settings dialog introduction', 'myplan-consent' ) ),
					'policy_page'       => array( 'page', __( 'Cookie policy page', 'myplan-consent' ) ),
					'privacy_page'      => array( 'page', __( 'Privacy notice page', 'myplan-consent' ) ),
				),
			),
			'consent'    => array(
				__( 'Consent validity and log', 'myplan-consent' ),
				array(
					'expiry_days'        => array( 'number', __( 'Ask again after (days)', 'myplan-consent' ), __( 'Applies to refusals too. Recommended: 180 (6 months): the EU Digital Omnibus proposal bars asking again within six months of a refusal. At most 395 (13 months).', 'myplan-consent' ) ),
					'reask'              => array( 'checkbox', __( 'Ask every visitor again on save', 'myplan-consent' ), __( 'Use when the categories or the purposes change.', 'myplan-consent' ) ),
					'respect_gpc'        => array( 'checkbox', __( 'Treat the browser’s Global Privacy Control signal as “reject all”', 'myplan-consent' ), __( 'The EU Digital Omnibus proposal would make browser-level signals binding; honouring them now is safe, since refusal is the default anyway.', 'myplan-consent' ) ),
					'log_enabled'        => array( 'checkbox', __( 'Keep a consent log (no IP address is stored)', 'myplan-consent' ) ),
					'log_retention_days' => array( 'number', __( 'Keep log entries for (days)', 'myplan-consent' ) ),
				),
			),
		);

		foreach ( $sections as $section => $def ) {
			add_settings_section( 'mpc_' . $section, $def[0], '__return_false', self::PAGE );

			foreach ( $def[1] as $key => $field ) {
				add_settings_field(
					'mpc_' . $key,
					$field[1],
					array( __CLASS__, 'field' ),
					self::PAGE,
					'mpc_' . $section,
					array(
						'key'       => $key,
						'type'      => $field[0],
						'extra'     => isset( $field[2] ) ? $field[2] : '',
						'label_for' => in_array( $field[0], array( 'checkbox' ), true ) ? null : 'mpc_' . $key,
					)
				);
			}
		}
	}

	/**
	 * Renders one field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function field( $args ) {
		$key   = $args['key'];
		$name  = MPC_Settings::OPTION . '[' . $key . ']';
		$id    = 'mpc_' . $key;
		$value = 'reask' === $key ? 0 : MPC_Settings::get( $key );
		$help  = is_string( $args['extra'] ) ? $args['extra'] : '';

		switch ( $args['type'] ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" name="%s" value="1" %s> %s</label>', esc_attr( $name ), checked( (bool) $value, true, false ), esc_html__( 'On', 'myplan-consent' ) );
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['extra'] as $opt => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="5" class="large-text code">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;
			case 'number':
				printf( '<input type="number" id="%s" name="%s" value="%d" class="small-text">', esc_attr( $id ), esc_attr( $name ), (int) $value );
				break;
			case 'color':
				printf( '<input type="color" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'page':
				wp_dropdown_pages(
					array(
						'id'                => esc_attr( $id ),
						'name'              => esc_attr( $name ),
						'selected'          => (int) $value,
						'show_option_none'  => esc_html__( '— None —', 'myplan-consent' ),
						'option_none_value' => 0,
					)
				);
				break;
			default:
				printf( '<input type="text" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
		}

		if ( $help ) {
			printf( '<p class="description">%s</p>', esc_html( $help ) );
		}
	}

	/**
	 * The settings screen.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats = MPC_Log::stats( 30 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cookie consent', 'myplan-consent' ); ?></h1>

			<div class="card" style="max-width:none">
				<h2><?php esc_html_e( 'Decisions in the last 30 days', 'myplan-consent' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: accept all count, 2: reject all count, 3: custom choice count, 4: count of embeds allowed one by one, 5: count of Global Privacy Control refusals. */
						esc_html__( 'Accepted all: %1$d · Rejected all: %2$d · Custom choice: %3$d · Allowed from an embed: %4$d · Browser refusal (GPC): %5$d', 'myplan-consent' ),
						(int) $stats['accept'],
						(int) $stats['reject'],
						(int) $stats['custom'],
						(int) $stats['embed'],
						(int) $stats['gpc']
					);
					?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mpc_export' ), 'mpc_export' ) ); ?>"><?php esc_html_e( 'Download the consent log (CSV)', 'myplan-consent' ); ?></a>
					<?php
					printf(
						/* translators: %d: policy version number. */
						' ' . esc_html__( 'Current policy version: %d', 'myplan-consent' ),
						(int) MPC_Settings::get( 'version' )
					);
					?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'myplan_consent' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * CSV download.
	 */
	public static function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'myplan-consent' ), 403 );
		}

		check_admin_referer( 'mpc_export' );
		MPC_Log::export_csv();
	}
}

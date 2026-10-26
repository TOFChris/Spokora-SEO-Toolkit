<?php
/**
 * Front-end cookie banner (UK/GDPR-style). Analytics loads only after accept.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Spokora_Consent {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 23 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front' ), 20 );
	}

	public function admin_menu() {
		$page_title = function_exists( 'spokora_consent_admin_page_title' )
			? spokora_consent_admin_page_title()
			: __( 'Cookie banner & privacy', 'spokora-seo-toolkit' );
		$menu_title = function_exists( 'spokora_consent_admin_menu_title' )
			? spokora_consent_admin_menu_title()
			: __( 'Cookies & privacy', 'spokora-seo-toolkit' );
		add_submenu_page(
			spokora_admin_menu_slug(),
			$page_title,
			$menu_title,
			'manage_options',
			'spokora-consent',
			array( $this, 'render_admin' )
		);
	}

	/**
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_admin( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'spokora-consent' ) ) {
			return;
		}
		wp_enqueue_style(
			'spokora-consent-admin',
			SPOKORA_PLUGIN_URL . 'assets/css/consent-admin.css',
			array(),
			SPOKORA_VERSION
		);
	}

	public function register_settings() {
		register_setting(
			'spokora_consent_group',
			'spokora_consent_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * @return array{privacy_url: string, ga_id: string, banner_enabled: bool}
	 */
	private function defaults() {
		return array(
			'privacy_url'     => '',
			'ga_id'           => '',
			'banner_enabled'  => false,
		);
	}

	/**
	 * @param mixed $value Raw.
	 * @return array{privacy_url: string, ga_id: string}
	 */
	public function sanitize( $value ) {
		$out = $this->defaults();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		if ( isset( $value['privacy_url'] ) ) {
			$out['privacy_url'] = esc_url_raw( wp_unslash( $value['privacy_url'] ) );
		}
		$raw = '';
		if ( isset( $value['ga_code'] ) ) {
			$raw = is_string( $value['ga_code'] ) ? wp_unslash( $value['ga_code'] ) : '';
		} elseif ( isset( $value['ga_id'] ) ) {
			$raw = is_string( $value['ga_id'] ) ? wp_unslash( $value['ga_id'] ) : '';
		}
		$extracted = function_exists( 'spokora_extract_ga_measurement_id' )
			? spokora_extract_ga_measurement_id( $raw )
			: '';
		if ( '' === trim( $raw ) ) {
			$out['ga_id'] = '';
		} elseif ( '' !== $extracted ) {
			$out['ga_id'] = $extracted;
			if ( false !== strpos( $raw, '<' ) ) {
				add_settings_error(
					'spokora_consent_settings',
					'spokora_ga_code',
					sprintf(
						/* translators: %s: GA4 Measurement ID */
						__( 'Saved Google Analytics as %s. Spokora loads the official gtag with Consent Mode after the visitor accepts — we do not print the pasted snippet as-is (that would skip the banner).', 'spokora-seo-toolkit' ),
						$extracted
					),
					'success'
				);
			}
		} else {
			$saved        = get_option( 'spokora_consent_settings', array() );
			$out['ga_id'] = ( is_array( $saved ) && ! empty( $saved['ga_id'] ) && function_exists( 'spokora_sanitize_ga_measurement_id' ) )
				? spokora_sanitize_ga_measurement_id( (string) $saved['ga_id'] )
				: '';
			add_settings_error(
				'spokora_consent_settings',
				'spokora_ga_code',
				__( 'Could not find a GA4 Measurement ID (starts with G-) in what you pasted. Paste the ID itself, or Google’s full tag that includes G-XXXX. Universal Analytics (UA-) is not supported.', 'spokora-seo-toolkit' ),
				'error'
			);
		}
		$out['banner_enabled'] = ! empty( $value['banner_enabled'] );
		return $out;
	}

	/**
	 * @return array{privacy_url: string, ga_id: string, banner_enabled: bool}
	 */
	private function settings() {
		$saved = get_option( 'spokora_consent_settings', false );
		if ( ! is_array( $saved ) ) {
			return $this->defaults();
		}
		$out = wp_parse_args( $saved, $this->defaults() );
		if ( ! array_key_exists( 'banner_enabled', $saved ) ) {
			$out['banner_enabled'] = true;
		} else {
			$out['banner_enabled'] = ! empty( $saved['banner_enabled'] );
		}
		return $out;
	}

	/**
	 * Front-end banner is off until the site owner turns it on (WordPress.org guideline 11).
	 *
	 * Existing installs that already saved cookie settings keep the banner.
	 *
	 * @return bool
	 */
	private function banner_enabled() {
		$s = $this->settings();
		return ! empty( $s['banner_enabled'] );
	}

	public function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s             = $this->settings();
		$page_title    = function_exists( 'spokora_consent_admin_page_title' )
			? spokora_consent_admin_page_title()
			: __( 'Cookie banner & privacy', 'spokora-seo-toolkit' );
		$fallback_url  = function_exists( 'spokora_consent_fallback_privacy_url' )
			? spokora_consent_fallback_privacy_url()
			: $this->fallback_privacy_url();
		$effective_url = function_exists( 'spokora_consent_effective_privacy_url' )
			? spokora_consent_effective_privacy_url()
			: $this->privacy_url();
		$checklist     = function_exists( 'spokora_consent_readiness_items' ) ? spokora_consent_readiness_items() : array();
		$privacy_page  = (int) get_option( 'wp_page_for_privacy_policy' );
		?>
		<div class="wrap spokora-consent-admin">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<?php settings_errors( 'spokora_consent_settings' ); ?>
			<p class="description spokora-consent-admin__lead">
				<?php
				echo esc_html__(
					'Set up a clear cookie banner for visitors in the UK, EU, and similar regions: Reject and Accept are equally easy, essential cookies keep the site running, and analytics tags load only after Accept. Pair this with a real privacy policy — Spokora gives you the technical controls; your counsel or client decides legal wording.',
					'spokora-seo-toolkit'
				);
				?>
			</p>

			<div class="spokora-consent-admin__grid">
				<section class="spokora-consent-admin__card" aria-labelledby="spokora-consent-checklist-title">
					<h2 id="spokora-consent-checklist-title"><?php esc_html_e( 'Compliance checklist', 'spokora-seo-toolkit' ); ?></h2>
					<p><?php esc_html_e( 'Work through these before you call the site done. Optional steps stay optional.', 'spokora-seo-toolkit' ); ?></p>
					<?php if ( $checklist ) : ?>
						<ul class="spokora-consent-admin__checklist">
							<?php foreach ( $checklist as $row ) : ?>
								<li>
									<span class="spokora-consent-admin__mark <?php echo ! empty( $row['ok'] ) ? 'is-ok' : 'is-pending'; ?>" aria-hidden="true">
										<?php echo ! empty( $row['ok'] ) ? '✓' : '○'; ?>
									</span>
									<div>
										<strong><?php echo esc_html( (string) $row['title'] ); ?></strong>
										<span class="description"><?php echo esc_html( (string) $row['detail'] ); ?></span>
										<?php if ( ! empty( $row['action_url'] ) && ! empty( $row['action_label'] ) ) : ?>
											<div class="spokora-consent-admin__actions">
												<a class="button button-secondary" href="<?php echo esc_url( (string) $row['action_url'] ); ?>"
													<?php echo ( (string) $row['action_url'] === home_url( '/' ) ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
												><?php echo esc_html( (string) $row['action_label'] ); ?></a>
											</div>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>

				<section class="spokora-consent-admin__card" aria-labelledby="spokora-consent-how-title">
					<h2 id="spokora-consent-how-title"><?php esc_html_e( 'What to do here', 'spokora-seo-toolkit' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Confirm Cookies & privacy is enabled under Modules.', 'spokora-seo-toolkit' ); ?></li>
						<li><?php esc_html_e( 'Publish a privacy policy and link it (WordPress privacy page or URL below).', 'spokora-seo-toolkit' ); ?></li>
						<li><?php esc_html_e( 'Open your site in a private window — you should see Reject and Accept before any analytics runs.', 'spokora-seo-toolkit' ); ?></li>
						<li><?php esc_html_e( 'Add a GA4 ID or Google’s tag below only if you need analytics; they wait for the same Accept cookie.', 'spokora-seo-toolkit' ); ?></li>
					</ol>
					<?php if ( function_exists( 'spokora_theme_handles_cookie_consent' ) && spokora_theme_handles_cookie_consent() ) : ?>
						<div class="notice notice-info inline"><p><?php esc_html_e( 'The active Spokora theme already shows this banner. Settings below still control the privacy link and GA4 ID the theme uses.', 'spokora-seo-toolkit' ); ?></p></div>
					<?php endif; ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: privacy policy URL */
							esc_html__( 'Banner will link to: %s', 'spokora-seo-toolkit' ),
							esc_html( $effective_url )
						);
						?>
					</p>
				</section>
			</div>

			<section class="spokora-consent-admin__card spokora-consent-admin__form-card" id="spokora-consent-banner">
				<h2><?php esc_html_e( 'Banner settings', 'spokora-seo-toolkit' ); ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'spokora_consent_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Front-end banner', 'spokora-seo-toolkit' ); ?></th>
							<td>
								<label for="spokora_consent_banner_enabled">
									<input type="hidden" name="spokora_consent_settings[banner_enabled]" value="0" />
									<input type="checkbox" id="spokora_consent_banner_enabled" name="spokora_consent_settings[banner_enabled]" value="1" <?php checked( ! empty( $s['banner_enabled'] ) ); ?> />
									<?php esc_html_e( 'Show the cookie banner on the public site', 'spokora-seo-toolkit' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Off by default on new installs. Turn this on if you want Reject / Accept on the front end. Optional Google Analytics still loads only after Accept.', 'spokora-seo-toolkit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spokora_consent_privacy_url"><?php esc_html_e( 'Privacy policy URL', 'spokora-seo-toolkit' ); ?></label></th>
							<td>
								<input type="url" class="large-text" id="spokora_consent_privacy_url" name="spokora_consent_settings[privacy_url]" value="<?php echo esc_attr( $s['privacy_url'] ); ?>" placeholder="<?php echo esc_attr( $fallback_url ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Leave blank to use the WordPress privacy page when set, otherwise /privacy-policy/.', 'spokora-seo-toolkit' ); ?>
									<?php
									if ( $privacy_page > 0 ) {
										$edit_privacy = get_edit_post_link( $privacy_page, 'raw' );
										if ( is_string( $edit_privacy ) && '' !== $edit_privacy ) {
											printf(
												' <a href="%1$s">%2$s</a>',
												esc_url( $edit_privacy ),
												esc_html__( 'Edit assigned privacy page', 'spokora-seo-toolkit' )
											);
										}
									}
									?>
								</p>
								<p>
									<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>"><?php esc_html_e( 'Open Settings → Privacy', 'spokora-seo-toolkit' ); ?></a>
									<?php if ( $privacy_page <= 0 ) : ?>
										<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Create a new page', 'spokora-seo-toolkit' ); ?></a>
									<?php endif; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="spokora_consent_ga_code"><?php esc_html_e( 'GA4 Measurement ID (optional)', 'spokora-seo-toolkit' ); ?></label></th>
							<td>
								<?php if ( defined( 'SPOKORA_GA_MEASUREMENT_ID' ) && is_string( SPOKORA_GA_MEASUREMENT_ID ) && '' !== SPOKORA_GA_MEASUREMENT_ID ) : ?>
									<code><?php echo esc_html( $this->ga_id() ); ?></code>
									<p class="description"><?php esc_html_e( 'Locked by SPOKORA_GA_MEASUREMENT_ID in wp-config.php.', 'spokora-seo-toolkit' ); ?></p>
								<?php else : ?>
									<textarea class="large-text code" rows="8" id="spokora_consent_ga_code" name="spokora_consent_settings[ga_code]" placeholder="<?php echo esc_attr( "G-XXXXXXXXXX\n\n" . __( 'or paste the whole Google tag (gtag.js) from Admin → Data streams.', 'spokora-seo-toolkit' ) ); ?>" autocomplete="off"><?php echo esc_textarea( isset( $s['ga_id'] ) ? $s['ga_id'] : '' ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Paste the Measurement ID (G-…) or the full script Google gives you. We pull out the ID and load gtag with Consent Mode only after Accept. Leave empty to skip analytics. Universal Analytics (UA-) is not supported.', 'spokora-seo-toolkit' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save banner settings', 'spokora-seo-toolkit' ) ); ?>
				</form>
			</section>

			<p class="spokora-consent-admin__disclaimer">
				<?php esc_html_e( 'Engineering controls only — not legal advice. Have your client or counsel review the privacy policy and cookie wording for their jurisdiction.', 'spokora-seo-toolkit' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * @return string
	 */
	private function fallback_privacy_url() {
		return function_exists( 'spokora_consent_fallback_privacy_url' )
			? spokora_consent_fallback_privacy_url()
			: home_url( '/privacy-policy/' );
	}

	/**
	 * @return string
	 */
	private function privacy_url() {
		return function_exists( 'spokora_consent_effective_privacy_url' )
			? spokora_consent_effective_privacy_url()
			: $this->fallback_privacy_url();
	}

	/**
	 * @return string
	 */
	private function ga_id() {
		return function_exists( 'spokora_analytics_measurement_id' ) ? spokora_analytics_measurement_id() : '';
	}

	public function enqueue_front() {
		if ( is_admin() || spokora_theme_handles_cookie_consent() || ! $this->banner_enabled() ) {
			return;
		}
		wp_enqueue_style(
			'spokora-consent',
			SPOKORA_PLUGIN_URL . 'assets/css/cookie-consent.css',
			array(),
			SPOKORA_VERSION
		);
		wp_enqueue_script(
			'spokora-consent',
			SPOKORA_PLUGIN_URL . 'assets/js/cookie-consent.js',
			array(),
			SPOKORA_VERSION,
			true
		);
		$debug = current_user_can( 'manage_options' ) && function_exists( 'spokora_query_arg' ) && null !== spokora_query_arg( 'spokora_ga_debug' );
		if ( function_exists( 'spokora_has_analytics_consent' ) && spokora_has_analytics_consent() ) {
			if ( function_exists( 'spokora_print_ga4_head_after_consent' ) ) {
				spokora_print_ga4_head_after_consent();
			}
		} elseif ( function_exists( 'spokora_print_ga4_consent_mode_defaults' ) ) {
			spokora_print_ga4_consent_mode_defaults();
		}
		wp_localize_script(
			'spokora-consent',
			'spokoraConsent',
			array(
				'measurementId' => $this->ga_id(),
				'debugMode'     => $debug,
				'privacyUrl'    => $this->privacy_url(),
				'strings'       => array(
					'dialogLabel' => __( 'Cookie choices', 'spokora-seo-toolkit' ),
					'body'        => __( 'This site uses essential cookies so WordPress can run (login and security). With your permission we may also use analytics cookies. Reject or accept non-essential cookies below — either choice is as easy as the other.', 'spokora-seo-toolkit' ),
					'privacyLink' => __( 'Privacy Policy', 'spokora-seo-toolkit' ),
					'reject'      => __( 'Reject non-essential cookies', 'spokora-seo-toolkit' ),
					'accept'      => __( 'Accept all cookies', 'spokora-seo-toolkit' ),
				),
			)
		);
	}
}

new Spokora_Consent();

<?php
/**
 * Cookie consent helpers (shared cookie name: spokora_consent).
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

if ( ! function_exists( 'spokora_theme_handles_cookie_consent' ) ) {
	/**
	 * Whether the active theme already prints the Spokora cookie banner.
	 *
	 * @return bool
	 */
	function spokora_theme_handles_cookie_consent() {
		$theme = get_template();
		return 'spokora' === $theme || function_exists( 'spokora_cookie_consent_script' );
	}
}

if ( ! function_exists( 'spokora_has_analytics_consent' ) ) {
	/**
	 * Whether the visitor accepted analytics (non-essential) cookies.
	 *
	 * @return bool
	 */
	function spokora_has_analytics_consent() {
		if ( empty( $_COOKIE['spokora_consent'] ) ) {
			return false;
		}
		return 'analytics' === sanitize_key( wp_unslash( $_COOKIE['spokora_consent'] ) );
	}
}

if ( ! function_exists( 'spokora_consent_admin_menu_title' ) ) {
	/**
	 * Short label in the Spokora flyout (Configure).
	 *
	 * @return string
	 */
	function spokora_consent_admin_menu_title() {
		return __( 'Cookies & privacy', 'spokora-seo-toolkit' );
	}
}

if ( ! function_exists( 'spokora_consent_admin_page_title' ) ) {
	/**
	 * Screen heading in wp-admin.
	 *
	 * @return string
	 */
	function spokora_consent_admin_page_title() {
		return __( 'Cookie banner & privacy', 'spokora-seo-toolkit' );
	}
}

if ( ! function_exists( 'spokora_consent_fallback_privacy_url' ) ) {
	/**
	 * @return string
	 */
	function spokora_consent_fallback_privacy_url() {
		if ( function_exists( 'get_privacy_policy_url' ) ) {
			$url = get_privacy_policy_url();
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/privacy-policy/' );
	}
}

if ( ! function_exists( 'spokora_consent_effective_privacy_url' ) ) {
	/**
	 * URL linked from the front-end banner.
	 *
	 * @return string
	 */
	function spokora_consent_effective_privacy_url() {
		$saved = get_option( 'spokora_consent_settings', array() );
		if ( is_array( $saved ) && ! empty( $saved['privacy_url'] ) ) {
			return (string) $saved['privacy_url'];
		}
		return spokora_consent_fallback_privacy_url();
	}
}

if ( ! function_exists( 'spokora_consent_privacy_policy_ready' ) ) {
	/**
	 * Whether a real privacy policy page is assigned or a custom URL is saved.
	 *
	 * @return bool
	 */
	function spokora_consent_privacy_policy_ready() {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $page_id > 0 && get_post_status( $page_id ) ) {
			return true;
		}
		$saved = get_option( 'spokora_consent_settings', array() );
		return is_array( $saved ) && ! empty( $saved['privacy_url'] );
	}
}

if ( ! function_exists( 'spokora_consent_module_enabled' ) ) {
	/**
	 * @return bool
	 */
	function spokora_consent_module_enabled() {
		return function_exists( 'spokora_is_module_enabled' ) && spokora_is_module_enabled( 'consent' );
	}
}

if ( ! function_exists( 'spokora_consent_banner_will_show' ) ) {
	/**
	 * Whether visitors should see a banner (plugin or Spokora theme).
	 *
	 * @return bool
	 */
	function spokora_consent_banner_will_show() {
		if ( ! spokora_consent_module_enabled() ) {
			return false;
		}
		return true;
	}
}

if ( ! function_exists( 'spokora_consent_readiness_items' ) ) {
	/**
	 * Setup checklist for the consent admin screen.
	 *
	 * @return list<array{ok: bool, title: string, detail: string, action_label?: string, action_url?: string}>
	 */
	function spokora_consent_readiness_items() {
		$module_on    = spokora_consent_module_enabled();
		$privacy_ok   = spokora_consent_privacy_policy_ready();
		$theme_banner = function_exists( 'spokora_theme_handles_cookie_consent' ) && spokora_theme_handles_cookie_consent();
		$banner_ok    = $module_on && spokora_consent_banner_will_show();
		$ga_id        = function_exists( 'spokora_analytics_measurement_id' ) ? spokora_analytics_measurement_id() : '';

		return array(
			array(
				'ok'           => $module_on,
				'title'        => __( 'Cookie banner module is on', 'spokora-seo-toolkit' ),
				'detail'       => __( 'Turn on Cookies & privacy under Modules so Spokora can show Reject and Accept on the public site.', 'spokora-seo-toolkit' ),
				'action_label' => __( 'Open Modules', 'spokora-seo-toolkit' ),
				'action_url'   => admin_url( 'admin.php?page=spokora-modules' ),
			),
			array(
				'ok'           => $privacy_ok,
				'title'        => __( 'Privacy policy is linked', 'spokora-seo-toolkit' ),
				'detail'       => __( 'Assign a page under Settings → Privacy, or paste your policy URL below. The banner links to it.', 'spokora-seo-toolkit' ),
				'action_label' => __( 'Privacy settings', 'spokora-seo-toolkit' ),
				'action_url'   => admin_url( 'options-privacy.php' ),
			),
			array(
				'ok'           => $banner_ok,
				'title'        => $theme_banner
					? __( 'Banner handled by your theme', 'spokora-seo-toolkit' )
					: __( 'Banner will show on the front end', 'spokora-seo-toolkit' ),
				'detail'       => $theme_banner
					? __( 'The Spokora theme already prints the same style of banner. Spokora SEO Toolkit will not duplicate it.', 'spokora-seo-toolkit' )
					: __( 'Visitors get equal Reject and Accept choices. Analytics waits until Accept.', 'spokora-seo-toolkit' ),
				'action_label' => __( 'Preview site', 'spokora-seo-toolkit' ),
				'action_url'   => home_url( '/' ),
			),
			array(
				'ok'           => true,
				'title'        => __( 'Google Analytics (optional)', 'spokora-seo-toolkit' ),
				'detail'       => '' !== $ga_id
					? __( 'GA4 is set and loads with Consent Mode — denied until the visitor accepts.', 'spokora-seo-toolkit' )
					: __( 'No GA4 ID yet — fine if you do not use analytics. Paste a G- ID or Google’s tag in Banner settings.', 'spokora-seo-toolkit' ),
				'action_label' => __( 'Banner settings', 'spokora-seo-toolkit' ),
				'action_url'   => admin_url( 'admin.php?page=spokora-consent#spokora-consent-banner' ),
			),
		);
	}
}

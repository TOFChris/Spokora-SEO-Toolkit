<?php
/**
 * Plugin Name: Spokora SEO Toolkit
 * Plugin URI: https://spokora.com
 * Description: SEO toolkit for WordPress websites: titles, schema, sitemap, and redirects. No account and no license key.
 * Version: 0.1.29
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Network: true
 * Author: That One Firm
 * Author URI: https://thatonefirm.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spokora-seo-toolkit
 *
 * Do not run next to Spokora Pro (plugin slug spokora). Pro already includes this SEO surface.
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

/**
 * Plugin basename for Spokora Pro (Woo zip folder `spokora/`).
 */
function spokora_free_pro_plugin_file() {
	return 'spokora/spokora.php';
}

/**
 * @param string $plugin Plugin basename or path fragment from a request.
 */
function spokora_free_matches_pro_plugin_file( $plugin ) {
	$plugin = (string) $plugin;
	$pro    = spokora_free_pro_plugin_file();
	if ( $pro === $plugin ) {
		return true;
	}
	$suffix = '/' . $pro;
	return strlen( $plugin ) >= strlen( $suffix ) && substr( $plugin, -strlen( $suffix ) ) === $suffix;
}

/**
 * True when this HTTP request is activating Spokora Pro (Pro is not active yet).
 */
function spokora_free_is_pro_activation_request() {
	if ( ! function_exists( 'wp_unslash' ) ) {
		return false;
	}
	$candidates = array();
	foreach ( array( 'plugin', 'checked', 'plugins' ) as $key ) {
		if ( empty( $_REQUEST[ $key ] ) ) {
			continue;
		}
		$val = wp_unslash( $_REQUEST[ $key ] );
		if ( is_string( $val ) ) {
			$candidates[] = $val;
		} elseif ( is_array( $val ) ) {
			foreach ( $val as $item ) {
				if ( is_string( $item ) ) {
					$candidates[] = $item;
				}
			}
		}
	}
	foreach ( $candidates as $plugin ) {
		if ( spokora_free_matches_pro_plugin_file( $plugin ) ) {
			return true;
		}
	}
	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( in_array( $action, array( 'activate', 'activate-selected', 'activate-plugin' ), true ) ) {
		if ( isset( $_REQUEST['slug'] ) && 'spokora' === sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether the paid Spokora plugin is already loaded or active.
 */
function spokora_free_pro_is_present() {
	if ( defined( 'SPOKORA_IS_PRO' ) && SPOKORA_IS_PRO ) {
		return true;
	}
	if ( function_exists( 'spokora_is_license_server' ) ) {
		return true;
	}
	if ( spokora_free_is_pro_activation_request() ) {
		return true;
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$file = spokora_free_pro_plugin_file();
	return is_plugin_active( $file ) || is_plugin_active_for_network( $file );
}

if ( spokora_free_pro_is_present() ) {
	if ( ! spokora_free_is_pro_activation_request() ) {
		add_action(
			'admin_notices',
			static function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-info"><p>';
				echo esc_html__( 'Spokora Pro is active, so Spokora SEO Toolkit stayed off. Pro already includes this SEO surface. Deactivate Spokora SEO Toolkit to avoid a duplicate plugin.', 'spokora-seo-toolkit' );
				echo '</p></div>';
			}
		);
	}
	return;
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * PHP 7.4 polyfill.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle Needle.
	 * @return bool
	 */
	function str_starts_with( $haystack, $needle ) {
		if ( '' === (string) $needle ) {
			return true;
		}
		return 0 === strncmp( (string) $haystack, (string) $needle, strlen( (string) $needle ) );
	}
}

define( 'SPOKORA_FREE', true );
define( 'SPOKORA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPOKORA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPOKORA_PLUGIN_FILE', __FILE__ );

require_once SPOKORA_PLUGIN_DIR . 'includes/version.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/legacy-keys.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/consent-helpers.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/analytics-helpers.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/admin-ui-helpers.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/home-intro.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/admin-shell.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/plugin-plugins-screen.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/pro-teaser.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/http-request.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/module-help.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/module-manifest.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/bootstrap-modules.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/multisite.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/theme-compat.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/gemini-content.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/post-keywords.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/schema-json-presets.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/seo-scoring.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/llms-hub-guard.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/rest-post-meta.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/heading-ids.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/article-markdown.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/content-images.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/content-types.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/schema-type-choices.php';
require_once SPOKORA_PLUGIN_DIR . 'includes/indexnow.php';

if ( is_admin() ) {
	require_once SPOKORA_PLUGIN_DIR . 'includes/hosting-requirements.php';
	require_once SPOKORA_PLUGIN_DIR . 'includes/legacy-seo-plugins.php';
	require_once SPOKORA_PLUGIN_DIR . 'includes/editor-admin-bridge.php';
	require_once SPOKORA_PLUGIN_DIR . 'includes/editor-metabox-ui.php';
	require_once SPOKORA_PLUGIN_DIR . 'includes/class-spokora-onboarding.php';
	new Spokora_Onboarding();
	require_once SPOKORA_PLUGIN_DIR . 'includes/class-spokora-list-quick-edit.php';
	require_once SPOKORA_PLUGIN_DIR . 'includes/class-spokora-help.php';
	new Spokora_Help();
	require_once SPOKORA_PLUGIN_DIR . 'includes/class-spokora-modules-admin.php';
	new Spokora_Modules_Admin();
	new Spokora_Go_Pro();
}

register_activation_hook( __FILE__, 'spokora_on_activate' );

spokora_load_modules();
spokora_migrate_legacy_hw_storage();

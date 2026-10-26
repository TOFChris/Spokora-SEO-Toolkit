<?php
/**
 * Module loader for Spokora SEO Toolkit.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

/**
 * Default on/off. Free has no Pro modules in the manifest.
 *
 * @return array<string, int>
 */
function spokora_get_default_modules() {
	$defaults = array();
	foreach ( array_keys( spokora_get_module_manifest() ) as $id ) {
		$defaults[ $id ] = 1;
	}
	return apply_filters( 'spokora_default_modules', $defaults );
}

/**
 * @return array<string, int>
 */
function spokora_get_merged_modules() {
	$saved = get_option( 'spokora_modules', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, spokora_get_default_modules() );
}

/**
 * Settings stays on. Everything else follows the Modules screen.
 *
 * @param string $id Module id.
 * @return bool
 */
function spokora_is_module_enabled( $id ) {
	$forced = apply_filters( 'spokora_forced_modules', array( 'settings' ) );
	if ( in_array( $id, $forced, true ) ) {
		return (bool) apply_filters( 'spokora_module_enabled', true, $id );
	}
	$mods = spokora_get_merged_modules();
	$on   = ! empty( $mods[ $id ] );
	return (bool) apply_filters( 'spokora_module_enabled', $on, $id );
}

/**
 * Load enabled modules.
 */
function spokora_load_modules() {
	foreach ( spokora_get_module_manifest() as $id => $meta ) {
		if ( ! spokora_is_module_enabled( $id ) ) {
			continue;
		}
		$path = SPOKORA_PLUGIN_DIR . 'modules/' . $meta['file'];
		if ( ! file_exists( $path ) ) {
			continue;
		}
		require_once $path;
		if ( 'loader' === $meta['bootstrap'] && ! empty( $meta['class'] ) && class_exists( $meta['class'] ) ) {
			new $meta['class']();
		}
	}
}

/**
 * @param array<string, mixed>|null $input Raw POST.
 * @return array<string, int>
 */
function spokora_sanitize_modules_option( $input ) {
	if ( ! is_array( $input ) ) {
		$input = array();
	}
	$forced = apply_filters( 'spokora_forced_modules', array( 'settings' ) );
	$out    = array();
	foreach ( array_keys( spokora_get_module_manifest() ) as $id ) {
		if ( in_array( $id, $forced, true ) ) {
			$out[ $id ] = 1;
			continue;
		}
		$out[ $id ] = ( isset( $input[ $id ] ) && (int) $input[ $id ] === 1 ) ? 1 : 0;
	}
	return $out;
}

/**
 * @param bool $from_activation From register_activation_hook.
 */
function spokora_sync_version_and_prompt( $from_activation = false ) {
	$current     = defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '0';
	$stored      = get_option( 'spokora_free_plugin_version_stored', '' );
	$has_modules = ( false !== get_option( 'spokora_modules', false ) );

	if ( ! $has_modules ) {
		if ( $from_activation ) {
			add_option( 'spokora_modules', spokora_get_default_modules() );
			set_transient( 'spokora_activation_redirect', 1, 120 );
			update_option( 'spokora_onboarding_pending', '1' );
		}
		update_option( 'spokora_free_plugin_version_stored', $current );
		return;
	}

	if ( (string) $stored === (string) $current ) {
		return;
	}

	$should_prompt = ( '' === (string) $stored || version_compare( (string) $stored, $current, '<' ) );
	update_option( 'spokora_free_plugin_version_stored', $current );

	if ( ! $should_prompt ) {
		return;
	}
	if ( $from_activation && get_transient( 'spokora_activation_redirect' ) ) {
		return;
	}

	set_transient( 'spokora_upgrade_wizard_redirect', 1, 300 );
	update_option( 'spokora_onboarding_pending', '1' );
}

/**
 * Rewrites + first-run flags for the current blog.
 *
 * @param bool $offer_wizard True to set the Quick setup redirect on this request.
 */
function spokora_activate_current_blog( $offer_wizard = true ) {
	add_rewrite_rule( '^llms\.txt$', 'index.php?spokora_llms_hub=1', 'top' );
	add_rewrite_rule( '^ai-docs/([^/]+)\.md$', 'index.php?spokora_ai_doc_slug=$matches[1]', 'top' );
	add_rewrite_rule( '^md/([^/]+)\.md$', 'index.php?spokora_article_md=$matches[1]', 'top' );
	flush_rewrite_rules();
	if ( $offer_wizard ) {
		spokora_sync_version_and_prompt( true );
		return;
	}
	if ( false === get_option( 'spokora_modules', false ) ) {
		add_option( 'spokora_modules', spokora_get_default_modules() );
		update_option( 'spokora_onboarding_pending', '1' );
	}
	$current = defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '0';
	update_option( 'spokora_free_plugin_version_stored', $current );
}

/**
 * Activation. Network Activate seeds every site in the network.
 *
 * @param bool $network_wide True when Network Activate runs.
 */
function spokora_on_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide && function_exists( 'spokora_free_bootstrap_network' ) ) {
		spokora_free_bootstrap_network( get_current_blog_id() );
		return;
	}
	spokora_activate_current_blog( true );
}

add_action(
	'plugins_loaded',
	static function () {
		spokora_sync_version_and_prompt( false );
	},
	8
);

<?php
/**
 * Uninstall Spokora SEO Toolkit.
 *
 * Does not delete spokora_options or post SEO meta. Those keys are shared with
 * Spokora Pro; wiping them would punish someone who is switching plugins.
 *
 * @package Spokora
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drop Free-only flags (not SEO post meta / spokora_options).
 */
function spokora_free_uninstall_blog_flags() {
	delete_option( 'spokora_free_plugin_version_stored' );
	delete_option( 'spokora_onboarding_pending' );
	delete_transient( 'spokora_activation_redirect' );
	delete_transient( 'spokora_upgrade_wizard_redirect' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields'   => 'ids',
			'number'   => 500,
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
		)
	);
	foreach ( (array) $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		spokora_free_uninstall_blog_flags();
		restore_current_blog();
	}
} else {
	spokora_free_uninstall_blog_flags();
}

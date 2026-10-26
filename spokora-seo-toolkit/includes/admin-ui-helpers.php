<?php
/**
 * Shared admin UI for Spokora SEO Toolkit.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin basename (folder/file).
 *
 * @return string
 */
function spokora_free_plugin_basename() {
	if ( defined( 'SPOKORA_PLUGIN_FILE' ) ) {
		return plugin_basename( SPOKORA_PLUGIN_FILE );
	}
	return 'spokora-seo-toolkit/spokora-seo-toolkit.php';
}

/**
 * Public product site.
 */
function spokora_product_url() {
	return apply_filters( 'spokora_product_url', 'https://spokora.com' );
}

/**
 * WordPress.org plugin support (directory listing).
 *
 * @return string
 */
function spokora_wporg_support_url() {
	return apply_filters( 'spokora_wporg_support_url', 'https://wordpress.org/support/plugin/spokora-seo-toolkit/' );
}

if ( ! function_exists( 'spokora_forum_url' ) ) {
	/**
	 * @return string
	 */
	function spokora_forum_url() {
		return apply_filters( 'spokora_forum_url', 'https://forum.spokora.com/' );
	}
}

/**
 * Public changelog.
 *
 * @return string
 */
function spokora_updates_page_url() {
	return apply_filters( 'spokora_updates_page_url', 'https://spokora.com/updates/' );
}

/**
 * Email list for Free releases (no site registration).
 *
 * @return string
 */
function spokora_updates_signup_url() {
	return apply_filters( 'spokora_updates_signup_url', 'https://spokora.com/updates/#signup' );
}

/**
 * WordPress.org reviews once the directory listing is live.
 *
 * @return string
 */
function spokora_free_review_url() {
	return apply_filters( 'spokora_free_review_url', 'https://wordpress.org/support/plugin/spokora-seo-toolkit/reviews/#new-post' );
}

/**
 * @return array<string, array{label: string, pages: list<string>, needles?: list<string>}>
 */
function spokora_admin_areas() {
	return array(
		'create'   => array(
			'label' => __( 'Create', 'spokora-seo-toolkit' ),
			'pages' => array( 'spokora-llms' ),
		),
		'optimize' => array(
			'label' => __( 'Optimize', 'spokora-seo-toolkit' ),
			'pages' => array( 'spokora-seo-scores', 'spokora-redirects', 'spokora-indexnow' ),
		),
		'results'  => array(
			'label' => __( 'Results', 'spokora-seo-toolkit' ),
			'pages' => array( 'spokora-markdown' ),
		),
		'setup'    => array(
			'label' => __( 'Configure', 'spokora-seo-toolkit' ),
			'pages' => array(
				'spokora-setup',
				'spokora-modules',
				'spokora-settings',
				'spokora-legacy-import',
				'spokora-backups',
				'spokora-consent',
				'spokora-help',
			),
		),
	);
}

/**
 * @param string $hook Admin hook.
 */
function spokora_enqueue_admin_menu( $hook ) {
	unset( $hook );
	$slug = spokora_admin_menu_slug();
	wp_enqueue_style(
		'spokora-admin-menu',
		SPOKORA_PLUGIN_URL . 'assets/admin-menu.css',
		array(),
		SPOKORA_VERSION
	);
	wp_enqueue_script(
		'spokora-admin-menu',
		SPOKORA_PLUGIN_URL . 'assets/admin-menu.js',
		array(),
		SPOKORA_VERSION,
		true
	);
	$areas = array();
	foreach ( spokora_admin_areas() as $id => $area ) {
		$areas[] = array(
			'id'      => $id,
			'label'   => $area['label'],
			'pages'   => $area['pages'],
			'needles' => isset( $area['needles'] ) ? $area['needles'] : array(),
		);
	}
	$areas[] = array(
		'id'    => 'more',
		'label' => __( 'More', 'spokora-seo-toolkit' ),
		'pages' => array(),
	);
	wp_localize_script(
		'spokora-admin-menu',
		'spokoraAdminMenu',
		array(
			'menuId' => 'toplevel_page_' . $slug,
			'home'   => $slug,
			'areas'  => $areas,
			'pinned' => spokora_admin_menu_pinned_pages(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'spokora_enqueue_admin_menu' );

/**
 * Pin Home-adjacent items so the flyout does not bury the upsell.
 */
function spokora_admin_menu_pinned_pages() {
	return array();
}

/**
 * Blank subnav (same contract as Pro so copied screens do not fatal).
 *
 * @param string $active Unused.
 */
function spokora_render_subnav( $active = 'dashboard' ) {
	unset( $active );
}

/**
 * @param string                $message Escaped or wp_kses message.
 * @param array<string, string> $links   Label => URL.
 */
function spokora_render_admin_tip( $message, array $links = array() ) {
	echo '<div class="spokora-admin-tip"><p>' . wp_kses_post( $message );
	if ( $links ) {
		echo ' ';
		$parts = array();
		foreach ( $links as $label => $url ) {
			$parts[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo wp_kses_post( implode( ' · ', $parts ) );
	}
	echo '</p></div>';
}

<?php
/**
 * Free admin home + menu slugs (no Live Dashboard telemetry).
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Top-level admin menu slug.
 */
function spokora_admin_menu_slug() {
	return apply_filters( 'spokora_admin_menu_slug', 'spokora-seo-toolkit' );
}

/**
 * Home page slug (same as menu).
 */
function spokora_dashboard_page_slug() {
	return spokora_admin_menu_slug();
}

/**
 * Business Info submenu slug.
 */
function spokora_settings_page_slug() {
	return 'spokora-settings';
}

/**
 * Register the Free home screen so other modules can attach submenus.
 */
function spokora_free_register_menu() {
	$parent = spokora_admin_menu_slug();
	$cap    = 'manage_options';
	add_menu_page(
		__( 'Spokora SEO Toolkit', 'spokora-seo-toolkit' ),
		__( 'Spokora', 'spokora-seo-toolkit' ),
		$cap,
		$parent,
		'spokora_free_render_home',
		SPOKORA_PLUGIN_URL . 'assets/menu-icon.png',
		58
	);
	add_submenu_page(
		$parent,
		__( 'Home', 'spokora-seo-toolkit' ),
		__( 'Home', 'spokora-seo-toolkit' ),
		$cap,
		$parent,
		'spokora_free_render_home'
	);
}
add_action( 'admin_menu', 'spokora_free_register_menu', 3 );

/**
 * How long Chris’s Home note stays hidden after dismiss (then it returns).
 */
function spokora_home_note_snooze_seconds() {
	return (int) apply_filters( 'spokora_home_note_snooze_seconds', 30 * DAY_IN_SECONDS );
}

/**
 * Dismiss the Home note until the snooze expires.
 */
function spokora_handle_snooze_home_note() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'spokora-seo-toolkit' ) );
	}
	check_admin_referer( 'spokora_snooze_home_note' );
	update_user_meta( get_current_user_id(), 'spokora_home_note_hidden_until', time() + spokora_home_note_snooze_seconds() );
	$back = wp_get_referer();
	if ( ! is_string( $back ) || '' === $back ) {
		$back = admin_url( 'admin.php?page=' . spokora_dashboard_page_slug() );
	}
	wp_safe_redirect( add_query_arg( 'spokora_note', 'snoozed', $back ) );
	exit;
}
add_action( 'admin_post_spokora_snooze_home_note', 'spokora_handle_snooze_home_note' );

/**
 * Home: SEO map + Pro card. No empty GSC / rank widgets.
 */
function spokora_free_render_home() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	?>
	<div class="wrap spokora-free-home">
		<h1><?php echo esc_html__( 'Spokora SEO Toolkit', 'spokora-seo-toolkit' ); ?></h1>
		<?php
		if ( function_exists( 'spokora_render_home_intro' ) ) {
			spokora_render_home_intro();
		}
		?>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: site hostname */
					__( 'SEO on this WordPress website (%s). No account and no license key.', 'spokora-seo-toolkit' ),
					$host ? $host : home_url()
				)
			);
			?>
		</p>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Dismissible admin notice flash.
		if ( isset( $_GET['spokora_note'] ) && 'snoozed' === sanitize_key( wp_unslash( $_GET['spokora_note'] ) ) ) :
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Got it. This note will come back in about a month.', 'spokora-seo-toolkit' ); ?></p></div>
		<?php endif; ?>
		<div class="spokora-home-grid">
			<section class="spokora-home-panel">
				<h2><?php echo esc_html__( 'What is running', 'spokora-seo-toolkit' ); ?></h2>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-setup&step=1' ) ); ?>"><?php echo esc_html__( 'Quick setup', 'spokora-seo-toolkit' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-legacy-import' ) ); ?>"><?php echo esc_html__( 'Import SEO', 'spokora-seo-toolkit' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-redirects' ) ); ?>"><?php echo esc_html__( 'Redirects', 'spokora-seo-toolkit' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-llms' ) ); ?>"><?php echo esc_html__( 'AI index', 'spokora-seo-toolkit' ); ?></a>
				</p>
				<ul>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-settings' ) ); ?>"><?php echo esc_html__( 'Business Info', 'spokora-seo-toolkit' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-seo-scores' ) ); ?>"><?php echo esc_html__( 'SEO scores', 'spokora-seo-toolkit' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-markdown' ) ); ?>"><?php echo esc_html__( 'Markdown copies', 'spokora-seo-toolkit' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-indexnow' ) ); ?>"><?php echo esc_html__( 'Search ping (IndexNow)', 'spokora-seo-toolkit' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-consent' ) ); ?>"><?php echo esc_html__( 'Cookies & privacy', 'spokora-seo-toolkit' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-help' ) ); ?>"><?php echo esc_html__( 'Help', 'spokora-seo-toolkit' ); ?></a></li>
				</ul>
			</section>
			<?php
			if ( function_exists( 'spokora_render_pro_teaser' ) ) {
				spokora_render_pro_teaser( 'card' );
			}
			?>
		</div>
	</div>
	<?php
}

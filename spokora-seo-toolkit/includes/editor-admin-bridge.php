<?php
/**
 * Shared Spokora chrome on post / page / show / episode editors.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return bool
 */
function spokora_is_spokora_editor_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->base, array( 'post' ), true ) ) {
		return false;
	}
	$types = function_exists( 'spokora_editor_metabox_post_types' ) ? spokora_editor_metabox_post_types() : array();
	return in_array( $screen->post_type, $types, true );
}

/**
 * @param string $hook Admin hook suffix.
 */
function spokora_editor_bridge_enqueue( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	if ( ! spokora_is_spokora_editor_screen() ) {
		return;
	}
	wp_enqueue_style(
		'spokora-editor-bridge',
		SPOKORA_PLUGIN_URL . 'assets/admin-editor-bridge.css',
		array(),
		defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '1.0.0'
	);
}
add_action( 'admin_enqueue_scripts', 'spokora_editor_bridge_enqueue', 15 );

/**
 * @param WP_Post $post Current post.
 */
function spokora_render_editor_bridge_banner( $post ) {
	if ( ! $post instanceof WP_Post || ! spokora_is_spokora_editor_screen() ) {
		return;
	}
	if ( $post->ID > 0 ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
	} else {
		$type_obj = get_post_type_object( $post->post_type );
		if ( ! $type_obj || ! current_user_can( $type_obj->cap->create_posts ) ) {
			return;
		}
	}

	$dash = admin_url( 'admin.php?page=' . spokora_dashboard_page_slug() );
	$type = $post->post_type;
	$obj  = get_post_type_object( $type );
	$label = $obj ? $obj->labels->singular_name : $type;

	$context = '';
	if ( 'spokora_show' === $type && $post->ID > 0 && function_exists( 'spokora_show_feed_url' ) ) {
		$feed = spokora_show_feed_url( $post->ID );
		if ( '' !== $feed ) {
			$context = __( 'RSS feed is live for this show.', 'spokora-seo-toolkit' );
		}
	}
	if ( 'spokora_episode' === $type ) {
		$show_id = (int) get_post_meta( $post->ID, '_spokora_episode_show', true );
		if ( $show_id <= 0 && isset( $_GET['spokora_show'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$show_id = absint( wp_unslash( $_GET['spokora_show'] ) );
		}
		if ( $show_id > 0 ) {
			$show_title = get_the_title( $show_id );
			$context    = sprintf(
				/* translators: %s: show title */
				__( 'Episode for show: %s', 'spokora-seo-toolkit' ),
				'' !== $show_title ? $show_title : '#' . $show_id
			);
		}
	}
	?>
	<div class="spokora-editor-bridge" role="navigation" aria-label="<?php esc_attr_e( 'Spokora', 'spokora-seo-toolkit' ); ?>"<?php echo function_exists( 'spokora_telemetry_brand_style_attr' ) ? spokora_telemetry_brand_style_attr() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="spokora-editor-bridge__row">
			<a class="spokora-editor-bridge__brand" href="<?php echo esc_url( $dash ); ?>"><?php esc_html_e( 'Spokora', 'spokora-seo-toolkit' ); ?></a>
			<span class="spokora-editor-bridge__type"><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $context ) : ?>
				<span class="spokora-editor-bridge__context">
					<?php echo esc_html( $context ); ?>
					<?php if ( 'spokora_show' === $type && $post->ID > 0 && function_exists( 'spokora_show_feed_url' ) ) : ?>
						<?php $feed = spokora_show_feed_url( $post->ID ); ?>
						<?php if ( '' !== $feed ) : ?>
							<a href="<?php echo esc_url( $feed ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open feed', 'spokora-seo-toolkit' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<span class="spokora-editor-bridge__links">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-setup&step=1' ) ); ?>"><?php esc_html_e( 'Quick setup', 'spokora-seo-toolkit' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Pages', 'spokora-seo-toolkit' ); ?></a>
			</span>
		</div>
	</div>
	<?php
}
add_action( 'edit_form_top', 'spokora_render_editor_bridge_banner', 5 );

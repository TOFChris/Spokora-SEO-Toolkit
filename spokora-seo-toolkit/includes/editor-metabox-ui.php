<?php
/**
 * Block editor metabox order and "Meta boxes" → Spokora labeling.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spokora meta box IDs in preferred tab order (Write → SEO → …).
 *
 * @return string[]
 */
function spokora_editor_metabox_order() {
	return array(
		'spokora-write-draft',
		'spokora_seo_meta_box',
		'spokora_social_syndicate',
		'spokora_llms_meta_box',
	);
}

/**
 * @param array $wp_meta_boxes Global meta boxes.
 * @return array
 */
function spokora_filter_block_editor_meta_boxes( $wp_meta_boxes ) {
	if ( ! is_array( $wp_meta_boxes ) ) {
		return $wp_meta_boxes;
	}

	$order = spokora_editor_metabox_order();
	foreach ( $wp_meta_boxes as $screen => $contexts ) {
		if ( ! is_array( $contexts ) ) {
			continue;
		}
		foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
			if ( empty( $contexts[ $context ] ) || ! is_array( $contexts[ $context ] ) ) {
				continue;
			}
			foreach ( $contexts[ $context ] as $priority => $boxes ) {
				if ( ! is_array( $boxes ) ) {
					continue;
				}
				$spokora = array();
				$rest    = array();
				foreach ( $boxes as $id => $box ) {
					if ( in_array( $id, $order, true ) ) {
						$spokora[ $id ] = $box;
					} else {
						$rest[ $id ] = $box;
					}
				}
				if ( empty( $spokora ) ) {
					continue;
				}
				$sorted = array();
				foreach ( $order as $id ) {
					if ( isset( $spokora[ $id ] ) ) {
						$sorted[ $id ] = $spokora[ $id ];
					}
				}
				foreach ( $spokora as $id => $box ) {
					if ( ! isset( $sorted[ $id ] ) ) {
						$sorted[ $id ] = $box;
					}
				}
				$wp_meta_boxes[ $screen ][ $context ][ $priority ] = array_merge( $sorted, $rest );
			}
		}
	}

	return $wp_meta_boxes;
}
add_filter( 'filter_block_editor_meta_boxes', 'spokora_filter_block_editor_meta_boxes', 20 );

/**
 * @return bool
 */
function spokora_editor_metabox_ui_screen_ok() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base ) {
		return false;
	}
	$types = function_exists( 'spokora_editor_metabox_post_types' ) ? spokora_editor_metabox_post_types() : array( 'post', 'page' );
	return in_array( $screen->post_type, $types, true );
}

/**
 * Gutenberg reads "Meta boxes" from the default script catalog — patch before edit-post renders.
 */
function spokora_editor_metabox_brand_i18n() {
	if ( ! spokora_editor_metabox_ui_screen_ok() ) {
		return;
	}

	$brand = __( 'Spokora', 'spokora-seo-toolkit' );
	wp_add_inline_script(
		'wp-i18n',
		'wp.i18n.setLocaleData( ' . wp_json_encode(
			array(
				'' => array(
					'Meta boxes' => array( $brand ),
					'Metaboxes'  => array( $brand ),
				),
			)
		) . ", 'default' );",
		'after'
	);
}
add_action( 'enqueue_block_editor_assets', 'spokora_editor_metabox_brand_i18n', 1 );

/**
 * PHP-rendered admin strings (classic metabox chrome, etc.).
 *
 * @param string $translated Translated text.
 * @param string $text       Source text.
 * @param string $domain     Text domain.
 * @return string
 */
function spokora_editor_metabox_brand_gettext( $translated, $text, $domain ) {
	if ( 'default' !== $domain || ! spokora_editor_metabox_ui_screen_ok() ) {
		return $translated;
	}
	if ( 'Meta boxes' === $text || 'Metaboxes' === $text ) {
		return __( 'Spokora', 'spokora-seo-toolkit' );
	}
	return $translated;
}
add_filter( 'gettext', 'spokora_editor_metabox_brand_gettext', 10, 3 );

/**
 * @param string $hook Admin hook (optional).
 */
function spokora_enqueue_editor_metabox_ui( $hook = '' ) {
	if ( '' !== $hook && ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	if ( ! spokora_editor_metabox_ui_screen_ok() ) {
		return;
	}

	wp_enqueue_script(
		'spokora-editor-metabox-ui',
		SPOKORA_PLUGIN_URL . 'assets/js/editor-metabox-ui.js',
		array( 'wp-i18n', 'wp-edit-post' ),
		defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '1.0.0',
		true
	);
	wp_localize_script(
		'spokora-editor-metabox-ui',
		'spokoraEditorMetaboxUi',
		array(
			'brandLabel' => __( 'Spokora', 'spokora-seo-toolkit' ),
			'boxOrder'   => array(
				'spokora_seo_meta_box',
				'spokora_llms_meta_box',
			),
			'metaLabels' => array(
				__( 'Meta boxes', 'spokora-seo-toolkit' ),
				'Meta boxes',
				'Metaboxes',
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'spokora_enqueue_editor_metabox_ui', 25 );
add_action( 'enqueue_block_editor_assets', 'spokora_enqueue_editor_metabox_ui', 25 );

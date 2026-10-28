<?php
/**
 * Register Spokora post meta on the REST API so fleet drafts can
 * save SEO / LLMS fields (and hit Search SEO + AI readiness 100) without wp-admin.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth: the user must be able to edit the specific post.
 *
 * @param bool   $allowed   Default.
 * @param string $meta_key  Meta key.
 * @param int    $object_id Post ID.
 * @return bool
 */
function spokora_rest_meta_auth( $allowed, $meta_key, $object_id ) {
	unset( $allowed, $meta_key );
	return current_user_can( 'edit_post', (int) $object_id );
}

/**
 * Register one string meta key with a custom sanitizer.
 *
 * @param string $key      Meta key.
 * @param string $sanitize Callable name.
 * @return void
 */
function spokora_register_rest_meta( $key, $sanitize ) {
	$args = array(
		'type'              => 'string',
		'single'            => true,
		'sanitize_callback' => $sanitize,
		'auth_callback'     => 'spokora_rest_meta_auth',
		'show_in_rest'      => array(
			'schema' => array(
				'type' => 'string',
			),
		),
	);
	$types = function_exists( 'spokora_scoring_post_types' )
		? spokora_scoring_post_types()
		: array( 'post', 'page' );
	foreach ( $types as $type ) {
		register_post_meta( $type, $key, $args );
	}
}

/**
 * Register one string meta key for scoring post types.
 *
 * @param string $key      Meta key.
 * @param bool   $textarea Long body (schema / markdown).
 * @return void
 */
function spokora_register_rest_string_meta( $key, $textarea = false ) {
	$schema = array(
		'type' => 'string',
	);
	if ( $textarea ) {
		$schema['format'] = 'textarea-plain';
	}
	$args = array(
		'type'              => 'string',
		'single'            => true,
		'sanitize_callback' => $textarea ? 'sanitize_textarea_field' : 'sanitize_text_field',
		'auth_callback'     => 'spokora_rest_meta_auth',
		'show_in_rest'      => array(
			'schema' => $schema,
		),
	);
	$types = function_exists( 'spokora_scoring_post_types' )
		? spokora_scoring_post_types()
		: array( 'post', 'page' );
	foreach ( $types as $type ) {
		register_post_meta( $type, $key, $args );
	}
}

/**
 * Register Spokora meta + authenticated scores route.
 *
 * @return void
 */
function spokora_register_rest_post_meta() {
	spokora_register_rest_string_meta( '_spokora_seo_title' );
	spokora_register_rest_string_meta( '_spokora_seo_desc', true );
	spokora_register_rest_meta(
		'_spokora_seo_canonical',
		'spokora_sanitize_canonical_url'
	);
	spokora_register_rest_meta(
		'_spokora_robots',
		'spokora_sanitize_robots_directive'
	);
	spokora_register_rest_string_meta( '_spokora_custom_schema', true );
	spokora_register_rest_string_meta( '_spokora_llms_public' );
	spokora_register_rest_string_meta( '_spokora_llms_include' );
	spokora_register_rest_string_meta( '_spokora_llms_summary' );
	spokora_register_rest_string_meta( '_spokora_llms_md', true );
	spokora_register_rest_string_meta( '_spokora_focus_keyword' );
	spokora_register_rest_string_meta( '_spokora_additional_keywords', true );
}
add_action( 'init', 'spokora_register_rest_post_meta', 20 );

/**
 * Authenticated Search SEO + AI readiness scores for fleet drafts.
 *
 * @return void
 */
function spokora_register_rest_scores_route() {
	register_rest_route(
		'spokora/v1',
		'/scores/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => static function ( WP_REST_Request $request ) {
				$post_id = (int) $request['id'];
				if ( $post_id < 1 || ! get_post( $post_id ) ) {
					return new WP_Error( 'spokora_missing_post', __( 'Post not found.', 'spokora-seo-toolkit' ), array( 'status' => 404 ) );
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return new WP_Error( 'spokora_forbidden', __( 'Cannot score this post.', 'spokora-seo-toolkit' ), array( 'status' => 403 ) );
				}
				if ( function_exists( 'spokora_compute_and_save_scores' ) ) {
					return rest_ensure_response( spokora_compute_and_save_scores( $post_id ) );
				}
				return rest_ensure_response( spokora_get_page_scores( $post_id ) );
			},
			'args'                => array(
				'id' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'spokora_register_rest_scores_route' );

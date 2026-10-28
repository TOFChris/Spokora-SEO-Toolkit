<?php
/**
 * Theme compatibility — when the active theme owns front-end SEO output.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Spokora should emit document title, meta description, canonical, robots, and JSON-LD.
 *
 * Admin/editor meta is always stored; this only gates front-end output.
 */
function spokora_should_output_seo() {
	$opts = get_option( 'spokora_options', array() );
	if ( is_array( $opts ) && isset( $opts['seo_output'] ) && '0' === (string) $opts['seo_output'] ) {
		return false;
	}
	return (bool) apply_filters( 'spokora_should_output_seo', true );
}

/**
 * Allowed per-page robots directives. Empty means the WordPress default.
 *
 * @return string[]
 */
function spokora_seo_robots_choices() {
	return array( 'noindex,follow', 'index,nofollow', 'noindex,nofollow' );
}

/**
 * @param mixed $value Raw meta or request value.
 * @return string
 */
function spokora_sanitize_robots_directive( $value ) {
	$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
	return in_array( $value, spokora_seo_robots_choices(), true ) ? $value : '';
}

/**
 * @param mixed $value Raw URL.
 * @return string Absolute http(s) URL, or empty.
 */
function spokora_sanitize_canonical_url( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( '' === $value ) {
		return '';
	}
	$value = esc_url_raw( $value );
	if ( '' === $value || ! wp_http_validate_url( $value ) ) {
		return '';
	}
	return $value;
}

/**
 * @param int $post_id Post ID.
 * @return string
 */
function spokora_get_post_robots_directive( $post_id ) {
	return spokora_sanitize_robots_directive( get_post_meta( (int) $post_id, '_spokora_robots', true ) );
}

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
 * Whether Spokora should emit document title, meta description, and JSON-LD.
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

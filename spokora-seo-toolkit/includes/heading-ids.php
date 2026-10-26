<?php
/**
 * Give headings a stable id when the editor did not set one.
 * Section links keep working, including links search engines already have.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $content Post content.
 * @return string
 */
function spokora_heading_ids( $content ) {
	if ( is_admin() || ! is_string( $content ) || false === stripos( $content, '<h' ) ) {
		return $content;
	}
	if ( ! apply_filters( 'spokora_heading_ids', true ) ) {
		return $content;
	}
	$used = array();
	return (string) preg_replace_callback(
		'/<h([2-4])(\s[^>]*)?>(.*?)<\/h\1>/is',
		static function ( $match ) use ( &$used ) {
			$attrs = isset( $match[2] ) ? (string) $match[2] : '';
			if ( preg_match( '/\sid\s*=/i', $attrs ) ) {
				return $match[0];
			}
			$text = trim( wp_strip_all_tags( (string) $match[3] ) );
			$id   = sanitize_title( $text );
			if ( '' === $id ) {
				return $match[0];
			}
			$base = $id;
			$n    = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . $n;
				++$n;
			}
			$used[ $id ] = true;
			return '<h' . $match[1] . $attrs . ' id="' . esc_attr( $id ) . '">' . $match[3] . '</h' . $match[1] . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'spokora_heading_ids', 12 );

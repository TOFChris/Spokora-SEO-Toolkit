<?php
/**
 * IndexNow: tell participating search engines when a public URL changes.
 * Google does not read IndexNow. The news sitemap is still the news path.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IndexNow is opt-in (outbound POST to api.indexnow.org).
 *
 * Existing sites that already pinged keep it on.
 *
 * @return bool
 */
function spokora_indexnow_is_enabled() {
	$stored = get_option( 'spokora_indexnow_enabled', 'unset' );
	if ( 'unset' === $stored ) {
		$last = get_option( 'spokora_indexnow_last', array() );
		$on   = is_array( $last ) && ! empty( $last['url'] );
		update_option( 'spokora_indexnow_enabled', $on ? '1' : '0', false );
		return $on;
	}
	return '1' === (string) $stored || 'yes' === (string) $stored || 1 === $stored || true === $stored;
}

/**
 * Site key hosted at /{key}.txt.
 */
function spokora_indexnow_key() {
	$key = (string) get_option( 'spokora_indexnow_key', '' );
	if ( ! preg_match( '/^[a-zA-Z0-9-]{8,128}$/', $key ) ) {
		try {
			$key = bin2hex( random_bytes( 16 ) );
		} catch ( Exception $e ) {
			$key = wp_generate_password( 32, false, false );
		}
		update_option( 'spokora_indexnow_key', $key, false );
	}
	return $key;
}

/**
 * @return string
 */
function spokora_indexnow_key_url() {
	return home_url( '/' . spokora_indexnow_key() . '.txt' );
}

/**
 * Serve the key file.
 */
function spokora_indexnow_maybe_serve() {
	if ( is_admin() || ! spokora_indexnow_is_enabled() ) {
		return;
	}
	$path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
	$key  = spokora_indexnow_key();
	if ( '/' . $key . '.txt' !== $path ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo esc_html( $key );
	exit;
}

/**
 * @param string $url Public URL.
 * @param bool   $blocking Wait for the response.
 * @return int HTTP code, or 0 when the request was not waited on.
 */
function spokora_indexnow_ping( $url, $blocking = false ) {
	if ( ! spokora_indexnow_is_enabled() ) {
		return 0;
	}
	$url = esc_url_raw( (string) $url );
	if ( '' === $url ) {
		return 0;
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$body = array(
		'host'        => $host,
		'key'         => spokora_indexnow_key(),
		'keyLocation' => spokora_indexnow_key_url(),
		'urlList'     => array( $url ),
	);
	$response = wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'timeout'  => $blocking ? 8 : 2,
			'blocking' => $blocking,
			'headers'  => array(
				'Content-Type' => 'application/json; charset=utf-8',
			),
			'body'     => wp_json_encode( $body ),
		)
	);
	$code = 0;
	if ( $blocking && ! is_wp_error( $response ) ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
	}
	update_option(
		'spokora_indexnow_last',
		array(
			'time' => time(),
			'url'  => $url,
			'code' => $code,
		),
		false
	);
	return $code;
}

/**
 * Ping when a public post is published, updated, or leaves publish.
 *
 * @param string  $new New status.
 * @param string  $old Old status.
 * @param WP_Post $post Post.
 */
function spokora_indexnow_on_status( $new, $old, $post ) {
	if ( ! spokora_indexnow_is_enabled() ) {
		return;
	}
	if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, spokora_scoring_post_types(), true ) ) {
		return;
	}
	if ( 'publish' !== $new && 'publish' !== $old ) {
		return;
	}
	$url = get_permalink( $post );
	if ( ! is_string( $url ) || '' === $url ) {
		return;
	}
	spokora_indexnow_ping( $url, false );
}

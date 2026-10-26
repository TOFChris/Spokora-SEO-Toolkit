<?php
/**
 * A markdown copy of every published post, separate from /llms.txt and /ai-docs/.
 *
 * /md/{slug}.md is generated from the post. It is not an AI document and it is not listed in the hub.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite for /md/{slug}.md.
 */
function spokora_article_md_register_rewrites() {
	add_rewrite_rule( '^md/([^/]+)\.md$', 'index.php?spokora_article_md=$matches[1]', 'top' );
}
add_action( 'init', 'spokora_article_md_register_rewrites', 4 );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function spokora_article_md_query_vars( $vars ) {
	$vars[] = 'spokora_article_md';
	return $vars;
}
add_filter( 'query_vars', 'spokora_article_md_query_vars' );

/**
 * Flush once per version so /md/{slug}.md resolves.
 */
function spokora_article_md_flush() {
	if ( (string) get_option( 'spokora_article_md_rewrite', '' ) === (string) SPOKORA_VERSION ) {
		return;
	}
	spokora_article_md_register_rewrites();
	flush_rewrite_rules( false );
	update_option( 'spokora_article_md_rewrite', SPOKORA_VERSION, false );
}
add_action( 'init', 'spokora_article_md_flush', 20 );

/**
 * Turn post HTML into markdown. Enough for headings, links, lists, and paragraphs.
 *
 * @param string $html HTML.
 */
function spokora_article_html_to_markdown( $html ) {
	$html = (string) $html;
	if ( '' === $html ) {
		return '';
	}
	$html = preg_replace( '#<(script|style|nav|header|footer|aside|noscript)[^>]*>.*?</\1>#is', '', $html );
	$html = preg_replace( '#<!--.*?-->#s', '', (string) $html );
	for ( $i = 6; $i >= 1; $i-- ) {
		$html = preg_replace_callback(
			'#<h' . $i . '[^>]*>(.*?)</h' . $i . '>#is',
			static function ( $match ) use ( $i ) {
				return str_repeat( '#', $i ) . ' ' . trim( wp_strip_all_tags( $match[1] ) ) . "\n\n";
			},
			(string) $html
		);
	}
	$html = preg_replace_callback(
		'#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
		static function ( $match ) {
			$text = trim( wp_strip_all_tags( $match[2] ) );
			$url  = esc_url_raw( $match[1] );
			if ( '' === $text ) {
				$text = $url;
			}
			return '[' . $text . '](' . $url . ')';
		},
		(string) $html
	);
	$html = preg_replace( '#</p\s*>#i', "\n\n", (string) $html );
	$html = preg_replace( '#<br\s*/?\s*>#i', "\n", (string) $html );
	$html = preg_replace( '#</li\s*>#i', "\n", (string) $html );
	$html = preg_replace( '#<li[^>]*>#i', '- ', (string) $html );
	$html = wp_strip_all_tags( (string) $html );
	$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$html = preg_replace( "/\n{3,}/", "\n\n", (string) $html );
	return trim( (string) $html );
}

/**
 * @param string $slug Post slug.
 * @return WP_Post|null
 */
function spokora_article_md_find( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return null;
	}
	$posts = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => spokora_scoring_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 5,
		)
	);
	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post && ! post_password_required( $post ) ) {
			return $post;
		}
	}
	return null;
}

/**
 * @param WP_Post $post Post.
 */
function spokora_article_md_body( WP_Post $post ) {
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core content filter.
	wp_reset_postdata();
	$body = spokora_article_html_to_markdown( is_string( $html ) ? $html : '' );
	if ( '' === $body ) {
		$body = trim( wp_strip_all_tags( $post->post_content ) );
	}
	$url   = get_permalink( $post );
	$lines = array(
		'# ' . get_the_title( $post ),
		'',
	);
	if ( is_string( $url ) && '' !== $url ) {
		$lines[] = 'Canonical: ' . $url;
		$lines[] = '';
	}
	$lines[] = $body;
	$lines[] = '';
	return implode( "\n", $lines );
}

/**
 * The Markdown module is on. New installs default to on.
 */
function spokora_article_md_is_on() {
	return ! function_exists( 'spokora_is_module_enabled' ) || spokora_is_module_enabled( 'markdown' );
}

/**
 * Serve /md/{slug}.md.
 */
function spokora_article_md_maybe_serve() {
	if ( is_admin() ) {
		return;
	}
	$slug = get_query_var( 'spokora_article_md' );
	if ( ! is_string( $slug ) || '' === $slug ) {
		$path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
		if ( preg_match( '#^/md/([^/]+)\.md$#', $path, $match ) ) {
			$slug = rawurldecode( $match[1] );
		}
	}
	if ( ! is_string( $slug ) || '' === $slug ) {
		return;
	}
	if ( ! spokora_article_md_is_on() ) {
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "Not found\n";
		exit;
	}
	$post = spokora_article_md_find( $slug );
	status_header( $post ? 200 : 404 );
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	if ( ! $post ) {
		echo "Not found\n";
		exit;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw markdown response; body is generated from post content.
	echo spokora_article_md_body( $post );
	exit;
}
add_action( 'template_redirect', 'spokora_article_md_maybe_serve', 0 );

/**
 * Point agents at the markdown copy without adding the post to /llms.txt.
 */
function spokora_article_md_link_header() {
	if ( is_admin() || headers_sent() || ! spokora_article_md_is_on() ) {
		return;
	}
	$post = null;
	if ( is_singular() ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$post = $queried;
		}
	}
	if ( ! $post instanceof WP_Post ) {
		$path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
		$slug = trim( $path, '/' );
		if ( '' === $slug || str_contains( $slug, '/' ) ) {
			return;
		}
		$post = spokora_article_md_find( $slug );
	}
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' === $post->post_name ) {
		return;
	}
	if ( ! in_array( $post->post_type, spokora_scoring_post_types(), true ) ) {
		return;
	}
	$url = home_url( '/md/' . rawurlencode( $post->post_name ) . '.md' );
	header( 'Link: <' . esc_url_raw( $url ) . '>; rel="alternate"; type="text/markdown"', false );
}
add_action( 'template_redirect', 'spokora_article_md_link_header', 20 );

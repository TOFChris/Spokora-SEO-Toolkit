<?php
/**
 * Legacy SEO plugin detection and safe deactivation (Spokora).
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Known legacy SEO / duplicate sitemap plugins (plugin basename => label).
 *
 * @return array<string, string>
 */
function spokora_get_legacy_seo_plugin_map() {
	return apply_filters(
		'spokora_legacy_seo_plugins',
		array(
			'wordpress-seo/wp-seo.php'                    => 'Yoast SEO',
			'wordpress-seo-premium/wp-seo-premium.php'    => 'Yoast SEO Premium',
			'seo-by-rank-math/rank-math.php'              => 'Rank Math',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'wp-seopress/seopress.php'                    => 'SEOPress',
			'wp-seopress-pro/seopress-pro.php'            => 'SEOPress Pro',
			'google-sitemap-generator/sitemap.php'        => 'Google XML Sitemaps',
		)
	);
}

/**
 * Active legacy SEO plugins on this site.
 *
 * @return array<string, string> basename => label
 */
function spokora_detect_active_legacy_seo_plugins() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$active = array();
	foreach ( spokora_get_legacy_seo_plugin_map() as $file => $label ) {
		if ( is_plugin_active( $file ) ) {
			$active[ $file ] = $label;
		}
	}
	return $active;
}

/**
 * Deactivate selected legacy SEO plugins.
 *
 * @param string[] $files Plugin basenames.
 * @return array{deactivated: string[], failed: string[]}
 */
function spokora_deactivate_legacy_seo_plugins( array $files ) {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$map          = spokora_get_legacy_seo_plugin_map();
	$deactivated  = array();
	$failed       = array();
	$allowed      = array_keys( $map );
	$to_deactivate = array();
	foreach ( $files as $file ) {
		$file = sanitize_text_field( $file );
		if ( ! in_array( $file, $allowed, true ) ) {
			continue;
		}
		if ( ! is_plugin_active( $file ) ) {
			continue;
		}
		$to_deactivate[] = $file;
	}
	if ( $to_deactivate ) {
		deactivate_plugins( $to_deactivate, true );
		foreach ( $to_deactivate as $file ) {
			if ( is_plugin_active( $file ) ) {
				$failed[] = $map[ $file ] ?? $file;
			} else {
				$deactivated[] = $map[ $file ] ?? $file;
			}
		}
	}
	return array(
		'deactivated' => $deactivated,
		'failed'      => $failed,
	);
}

/**
 * Turn a stored redirect into a path on this site, or an https URL for a target on another host.
 *
 * @param string $value         Stored URL or path.
 * @param bool   $allow_external Targets may leave this host. Sources may not.
 */
function spokora_legacy_redirect_location( $value, $allow_external = false ) {
	$value = trim( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
	if ( '' === $value || str_contains( $value, '|' ) || preg_match( '/[\r\n\s<>]/', $value ) ) {
		return '';
	}
	if ( preg_match( '#^(javascript|data):#i', $value ) ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $value ) ) {
		$host = strtolower( (string) wp_parse_url( $value, PHP_URL_HOST ) );
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$home = preg_replace( '/^www\./', '', $home );
		$host = preg_replace( '/^www\./', '', $host );
		$path = (string) wp_parse_url( $value, PHP_URL_PATH );
		if ( $host === $home ) {
			return spokora_legacy_redirect_path( $path );
		}
		if ( $allow_external ) {
			$url = esc_url_raw( $value );
			return is_string( $url ) && str_starts_with( $url, 'http' ) ? $url : '';
		}
		return '';
	}
	if ( str_starts_with( $value, '//' ) ) {
		return '';
	}
	return spokora_legacy_redirect_path( $value );
}

/**
 * @param string $path Path.
 */
function spokora_legacy_redirect_path( $path ) {
	$path = '/' . ltrim( (string) $path, '/' );
	if ( '/' === $path || str_contains( $path, '?' ) || str_contains( $path, '#' ) || str_contains( $path, '..' ) ) {
		return '';
	}
	return $path;
}

/**
 * @param string $table Full table name from a known prefix.
 * @return string[]
 */
function spokora_legacy_table_columns( $table ) {
	global $wpdb;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	if ( $found !== $table ) {
		return array();
	}
	$cols = $wpdb->get_col( "DESCRIBE `{$table}`", 0 );
	return is_array( $cols ) ? $cols : array();
}

/**
 * @param int $code HTTP status stored by the other plugin.
 */
function spokora_legacy_redirect_is_permanent( $code ) {
	$code = (int) $code;
	return 301 === $code || 308 === $code;
}

/**
 * Exact permanent redirects from Yoast, Rank Math, AIOSEO, and SEOPress.
 *
 * Pattern, query-string, and temporary redirects are counted and left out.
 *
 * @return array{added: int, skipped: int}
 */
function spokora_import_legacy_redirects() {
	$pairs = array();
	$skip  = 0;
	spokora_legacy_collect_yoast_redirects( $pairs, $skip );
	spokora_legacy_collect_rank_math_redirects( $pairs, $skip );
	spokora_legacy_collect_aioseo_redirects( $pairs, $skip );
	spokora_legacy_collect_seopress_redirects( $pairs, $skip );
	return spokora_legacy_merge_redirects( $pairs, $skip );
}

/**
 * @param array<int, array{0: string, 1: string}> $pairs From, to.
 * @param int                                      $skip Skipped count.
 */
function spokora_legacy_collect_yoast_redirects( array &$pairs, &$skip ) {
	global $wpdb;
	$table = $wpdb->prefix . 'yoast_seo_redirects';
	$cols  = spokora_legacy_table_columns( $table );
	if ( ! in_array( 'origin', $cols, true ) || ! in_array( 'target', $cols, true ) ) {
		return;
	}
	$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
	if ( ! is_array( $rows ) ) {
		return;
	}
	foreach ( $rows as $row ) {
		$format = isset( $row['format'] ) ? (string) $row['format'] : 'plain';
		$type   = isset( $row['type'] ) ? (int) $row['type'] : 301;
		if ( 'plain' !== $format || ! spokora_legacy_redirect_is_permanent( $type ) ) {
			++$skip;
			continue;
		}
		spokora_legacy_push_redirect( $pairs, $skip, (string) ( $row['origin'] ?? '' ), (string) ( $row['target'] ?? '' ) );
	}
}

/**
 * @param array<int, array{0: string, 1: string}> $pairs From, to.
 * @param int                                      $skip Skipped count.
 */
function spokora_legacy_collect_rank_math_redirects( array &$pairs, &$skip ) {
	global $wpdb;
	$table = $wpdb->prefix . 'rank_math_redirections';
	$cols  = spokora_legacy_table_columns( $table );
	if ( ! in_array( 'sources', $cols, true ) || ! in_array( 'url_to', $cols, true ) ) {
		return;
	}
	$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
	if ( ! is_array( $rows ) ) {
		return;
	}
	foreach ( $rows as $row ) {
		if ( isset( $row['status'] ) && 'active' !== (string) $row['status'] ) {
			++$skip;
			continue;
		}
		$code = isset( $row['header_code'] ) ? (int) $row['header_code'] : 301;
		if ( ! spokora_legacy_redirect_is_permanent( $code ) ) {
			++$skip;
			continue;
		}
		$raw     = $row['sources'] ?? '';
		$sources = maybe_unserialize( $raw );
		if ( ! is_array( $sources ) && is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$sources = $decoded;
			}
		}
		if ( ! is_array( $sources ) ) {
			++$skip;
			continue;
		}
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				++$skip;
				continue;
			}
			$comparison = isset( $source['comparison'] ) ? (string) $source['comparison'] : 'exact';
			if ( 'exact' !== $comparison ) {
				++$skip;
				continue;
			}
			spokora_legacy_push_redirect( $pairs, $skip, (string) ( $source['pattern'] ?? '' ), (string) ( $row['url_to'] ?? '' ) );
		}
	}
}

/**
 * @param array<int, array{0: string, 1: string}> $pairs From, to.
 * @param int                                      $skip Skipped count.
 */
function spokora_legacy_collect_aioseo_redirects( array &$pairs, &$skip ) {
	global $wpdb;
	$table = $wpdb->prefix . 'aioseo_redirects';
	$cols  = spokora_legacy_table_columns( $table );
	$source_col = in_array( 'source_url', $cols, true ) ? 'source_url' : '';
	$target_col = in_array( 'target_url', $cols, true ) ? 'target_url' : '';
	if ( '' === $source_col || '' === $target_col ) {
		return;
	}
	$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
	if ( ! is_array( $rows ) ) {
		return;
	}
	foreach ( $rows as $row ) {
		if ( isset( $row['enabled'] ) && ! (int) $row['enabled'] ) {
			++$skip;
			continue;
		}
		if ( ! empty( $row['regex'] ) ) {
			++$skip;
			continue;
		}
		$match = isset( $row['source_url_match'] ) ? strtolower( (string) $row['source_url_match'] ) : 'exact';
		if ( '' !== $match && 'exact' !== $match ) {
			++$skip;
			continue;
		}
		$code = isset( $row['type'] ) ? (int) $row['type'] : ( isset( $row['header'] ) ? (int) $row['header'] : 301 );
		if ( ! spokora_legacy_redirect_is_permanent( $code ) ) {
			++$skip;
			continue;
		}
		spokora_legacy_push_redirect( $pairs, $skip, (string) $row[ $source_col ], (string) $row[ $target_col ] );
	}
}

/**
 * @param array<int, array{0: string, 1: string}> $pairs From, to.
 * @param int                                      $skip Skipped count.
 */
function spokora_legacy_collect_seopress_redirects( array &$pairs, &$skip ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT p.post_title AS source_url, target.meta_value AS target_url, enabled.meta_value AS enabled, kind.meta_value AS kind, who.meta_value AS who
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} target ON target.post_id = p.ID AND target.meta_key = '_seopress_redirections_value'
		LEFT JOIN {$wpdb->postmeta} enabled ON enabled.post_id = p.ID AND enabled.meta_key = '_seopress_redirections_enabled'
		LEFT JOIN {$wpdb->postmeta} kind ON kind.post_id = p.ID AND kind.meta_key = '_seopress_redirections_type'
		LEFT JOIN {$wpdb->postmeta} who ON who.post_id = p.ID AND who.meta_key = '_seopress_redirections_logged_status'
		WHERE p.post_type = 'seopress_404' AND p.post_status = 'publish'"
	);
	if ( ! is_array( $rows ) ) {
		return;
	}
	foreach ( $rows as $row ) {
		$enabled = isset( $row->enabled ) ? (string) $row->enabled : '';
		if ( '' !== $enabled && 'yes' !== $enabled && '1' !== $enabled ) {
			++$skip;
			continue;
		}
		if ( isset( $row->who ) && 'only_logged_in' === (string) $row->who ) {
			++$skip;
			continue;
		}
		$type = isset( $row->kind ) ? (string) $row->kind : '';
		if ( '' !== $type && ! spokora_legacy_redirect_is_permanent( (int) $type ) ) {
			++$skip;
			continue;
		}
		spokora_legacy_push_redirect( $pairs, $skip, (string) $row->source_url, (string) $row->target_url );
	}
}

/**
 * @param array<int, array{0: string, 1: string}> $pairs From, to.
 * @param int                                      $skip Skipped count.
 * @param string                                   $from Source.
 * @param string                                   $to   Target.
 */
function spokora_legacy_push_redirect( array &$pairs, &$skip, $from, $to ) {
	$source = spokora_legacy_redirect_location( $from, false );
	$target = spokora_legacy_redirect_location( $to, true );
	if ( '' === $source || '' === $target || untrailingslashit( $source ) === untrailingslashit( $target ) ) {
		++$skip;
		return;
	}
	$pairs[] = array( $source, $target );
}

/**
 * Add new lines to the redirect list. Existing sources stay as they are.
 *
 * @param array<int, array{0: string, 1: string}> $pairs From, to.
 * @param int                                      $skip Already skipped.
 * @return array{added: int, skipped: int}
 */
function spokora_legacy_merge_redirects( array $pairs, $skip ) {
	$rules = (string) get_option( 'spokora_redirect_rules', '' );
	$have  = array();
	$lines = array();
	foreach ( explode( "\n", str_replace( "\r", '', $rules ) ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$lines[] = $line;
		if ( ! str_contains( $line, '|' ) ) {
			continue;
		}
		$old  = trim( explode( '|', $line, 2 )[0] );
		$path = spokora_legacy_redirect_location( $old, false );
		if ( '' !== $path ) {
			$have[ untrailingslashit( $path ) ] = true;
		}
	}
	$added = 0;
	foreach ( $pairs as $pair ) {
		$key = untrailingslashit( $pair[0] );
		if ( isset( $have[ $key ] ) ) {
			++$skip;
			continue;
		}
		if ( count( $lines ) >= 2000 ) {
			++$skip;
			continue;
		}
		$lines[]        = $pair[0] . ' | ' . $pair[1];
		$have[ $key ]   = true;
		++$added;
	}
	if ( $added > 0 ) {
		update_option( 'spokora_redirect_rules', implode( "\n", $lines ) );
	}
	return array(
		'added'   => $added,
		'skipped' => (int) $skip,
	);
}

/**
 * Sentence for the settings notice. Empty when this request did not import redirects.
 */
function spokora_legacy_redirect_result_sentence() {
	if ( ! isset( $_GET['redirects'] ) ) {
		return '';
	}
	$added = absint( wp_unslash( $_GET['redirects'] ) );
	$left  = isset( $_GET['redirects_left'] ) ? absint( wp_unslash( $_GET['redirects_left'] ) ) : 0;
	$text  = sprintf(
		/* translators: %d: redirects copied */
		__( 'Brought in %d redirects.', 'spokora-seo-toolkit' ),
		$added
	);
	if ( $left > 0 ) {
		$text .= ' ' . sprintf(
			/* translators: %d: redirects left behind */
			__( 'Left %d pattern or temporary redirects.', 'spokora-seo-toolkit' ),
			$left
		);
	}
	return $text;
}

/**
 * Parse a legacy plugin keyword field (JSON, Yoast objects, CSV, pipes).
 *
 * @param mixed $raw Stored meta.
 * @return string[]
 */
function spokora_legacy_parse_keyword_blob( $raw ) {
	if ( is_array( $raw ) ) {
		return function_exists( 'spokora_sanitize_keyword_list' )
			? spokora_sanitize_keyword_list( $raw )
			: array_values( array_filter( array_map( 'sanitize_text_field', $raw ) ) );
	}
	$text = trim( (string) $raw );
	if ( '' === $text ) {
		return array();
	}
	$json = json_decode( $text, true );
	if ( is_array( $json ) ) {
		$out = array();
		foreach ( $json as $item ) {
			if ( is_string( $item ) ) {
				$out[] = $item;
			} elseif ( is_array( $item ) ) {
				if ( isset( $item['keyword'] ) && is_string( $item['keyword'] ) ) {
					$out[] = $item['keyword'];
				} elseif ( isset( $item['keyphrase'] ) && is_string( $item['keyphrase'] ) ) {
					$out[] = $item['keyphrase'];
				}
			}
		}
		return function_exists( 'spokora_sanitize_keyword_list' )
			? spokora_sanitize_keyword_list( $out )
			: array_values( array_filter( array_map( 'sanitize_text_field', $out ) ) );
	}
	if ( str_contains( $text, '|' ) ) {
		$parts = explode( '|', $text );
	} else {
		$parts = preg_split( '/[\r\n,]+/', $text );
	}
	return function_exists( 'spokora_sanitize_keyword_list' )
		? spokora_sanitize_keyword_list( is_array( $parts ) ? $parts : array() )
		: array_values( array_filter( array_map( 'sanitize_text_field', (array) $parts ) ) );
}

/**
 * Read focus keyphrases from Yoast, Rank Math, AIOSEO, and SEOPress meta.
 *
 * @param int $post_id Post ID.
 * @return array{primary: string, additional: string[]}
 */
function spokora_legacy_extract_seo_keywords( $post_id ) {
	$post_id = (int) $post_id;
	$primary = '';
	$extra   = array();

	$rm_focus = trim( (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ) );
	$rm_more  = spokora_legacy_parse_keyword_blob( get_post_meta( $post_id, 'rank_math_keywords', true ) );
	if ( '' !== $rm_focus ) {
		$primary = $rm_focus;
	}
	foreach ( $rm_more as $kw ) {
		if ( $kw !== $primary ) {
			$extra[] = $kw;
		}
	}

	if ( '' === $primary ) {
		$yoast = trim( (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		if ( '' !== $yoast ) {
			$primary = $yoast;
		}
	}
	$yoast_more = spokora_legacy_parse_keyword_blob( get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true ) );
	foreach ( $yoast_more as $kw ) {
		if ( $kw !== $primary ) {
			$extra[] = $kw;
		}
	}

	if ( '' === $primary ) {
		$aioseo = trim( (string) get_post_meta( $post_id, '_aioseo_focus_keyphrase', true ) );
		if ( '' === $aioseo ) {
			$aioseo = trim( (string) get_post_meta( $post_id, '_aioseo_keyphrase', true ) );
		}
		if ( '' !== $aioseo ) {
			$primary = $aioseo;
		}
	}
	$aioseo_more = spokora_legacy_parse_keyword_blob( get_post_meta( $post_id, '_aioseo_keyphrases', true ) );
	if ( empty( $aioseo_more ) ) {
		$aioseo_more = spokora_legacy_parse_keyword_blob( get_post_meta( $post_id, '_aioseo_keywords', true ) );
	}
	foreach ( $aioseo_more as $kw ) {
		if ( $kw !== $primary ) {
			$extra[] = $kw;
		}
	}

	if ( '' === $primary ) {
		$sp = trim( (string) get_post_meta( $post_id, '_seopress_analysis_target_kw', true ) );
		if ( '' !== $sp ) {
			$primary = $sp;
		}
	}

	$extra = function_exists( 'spokora_sanitize_keyword_list' )
		? spokora_sanitize_keyword_list( $extra )
		: array_values( array_unique( array_filter( $extra ) ) );
	if ( '' === $primary && ! empty( $extra ) ) {
		$primary = $extra[0];
		$extra   = array_slice( $extra, 1 );
	}
	if ( $primary !== '' ) {
		$extra = array_values(
			array_filter(
				$extra,
				static function ( $kw ) use ( $primary ) {
					return $kw !== $primary;
				}
			)
		);
	}

	$limit = function_exists( 'spokora_post_keyword_limit' ) ? spokora_post_keyword_limit() : 5;
	if ( $primary !== '' ) {
		$extra = array_slice( $extra, 0, max( 0, $limit - 1 ) );
	} else {
		$extra = array_slice( $extra, 0, $limit );
	}

	return array(
		'primary'    => $primary,
		'additional' => $extra,
	);
}

/**
 * Copy legacy keyphrases into Spokora when the post has none yet.
 *
 * @param int $post_id Post ID.
 * @return int Number of meta fields written (0–2).
 */
function spokora_hijack_legacy_keywords_for_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id < 1 || ! function_exists( 'spokora_get_post_keywords' ) ) {
		return 0;
	}

	$legacy  = spokora_legacy_extract_seo_keywords( $post_id );
	$current = spokora_get_post_keywords( $post_id );
	$written = 0;

	if ( '' === $current['primary'] && '' !== $legacy['primary'] ) {
		update_post_meta( $post_id, '_spokora_focus_keyword', $legacy['primary'] );
		++$written;
	}

	if ( empty( $current['additional'] ) && ! empty( $legacy['additional'] ) ) {
		update_post_meta( $post_id, '_spokora_additional_keywords', wp_json_encode( $legacy['additional'] ) );
		++$written;
	} elseif ( ! empty( $current['additional'] ) && ! empty( $legacy['additional'] ) ) {
		$merged = function_exists( 'spokora_sanitize_keyword_list' )
			? spokora_sanitize_keyword_list( array_merge( $current['additional'], $legacy['additional'] ) )
			: array_values( array_unique( array_merge( $current['additional'], $legacy['additional'] ) ) );
		$limit  = function_exists( 'spokora_post_keyword_limit' ) ? spokora_post_keyword_limit() : 5;
		if ( $current['primary'] !== '' ) {
			$merged = array_values(
				array_filter(
					$merged,
					static function ( $kw ) use ( $current ) {
						return $kw !== $current['primary'];
					}
				)
			);
			$merged = array_slice( $merged, 0, max( 0, $limit - 1 ) );
		} else {
			$merged = array_slice( $merged, 0, $limit );
		}
		if ( $merged !== $current['additional'] ) {
			update_post_meta( $post_id, '_spokora_additional_keywords', wp_json_encode( $merged ) );
			++$written;
		}
	}

	return $written;
}

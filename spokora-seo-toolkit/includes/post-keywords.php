<?php
/**
 * Per-post focus keyphrases (primary + additional), Yoast/Rank Math–style.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Max keyphrases per post (primary + additional).
 *
 * @return int
 */
function spokora_post_keyword_limit() {
	return (int) apply_filters( 'spokora_post_keyword_limit', 5 );
}

/**
 * @param int $post_id Post ID.
 * @return array{primary: string, additional: string[]}
 */
function spokora_get_post_keywords( $post_id ) {
	$post_id = (int) $post_id;
	$primary = trim( (string) get_post_meta( $post_id, '_spokora_focus_keyword', true ) );

	$raw = get_post_meta( $post_id, '_spokora_additional_keywords', true );
	$additional = array();
	if ( is_string( $raw ) && $raw !== '' ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$additional = $decoded;
		}
	} elseif ( is_array( $raw ) ) {
		$additional = $raw;
	}

	$additional = spokora_sanitize_keyword_list( $additional );
	if ( $primary === '' && ! empty( $additional ) ) {
		$primary      = $additional[0];
		$additional   = array_slice( $additional, 1 );
	}

	return array(
		'primary'    => $primary,
		'additional' => $additional,
	);
}

/**
 * All keyphrases for scoring / rank sync (unique, primary first).
 *
 * @param int $post_id Post ID.
 * @return string[]
 */
function spokora_get_post_keyword_list( $post_id ) {
	$bundle = spokora_get_post_keywords( $post_id );
	$list   = array();
	if ( $bundle['primary'] !== '' ) {
		$list[] = $bundle['primary'];
	}
	foreach ( $bundle['additional'] as $kw ) {
		if ( $kw !== '' && ! in_array( $kw, $list, true ) ) {
			$list[] = $kw;
		}
	}
	return array_slice( $list, 0, spokora_post_keyword_limit() );
}

/**
 * @param string|array $input Lines, comma-separated string, or list.
 * @return string[]
 */
function spokora_parse_keyword_input( $input ) {
	if ( is_array( $input ) ) {
		return spokora_sanitize_keyword_list( $input );
	}
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $input );
	$parts = preg_split( '/[\n,]+/', $text );
	return spokora_sanitize_keyword_list( $parts ? $parts : array() );
}

/**
 * @param array $list Raw strings.
 * @return string[]
 */
function spokora_sanitize_keyword_list( array $list ) {
	$out = array();
	foreach ( $list as $item ) {
		$kw = trim( sanitize_text_field( (string) $item ) );
		if ( $kw === '' || in_array( $kw, $out, true ) ) {
			continue;
		}
		$out[] = $kw;
		if ( count( $out ) >= spokora_post_keyword_limit() ) {
			break;
		}
	}
	return $out;
}

/**
 * @param string $haystack Plain or HTML text.
 * @param string $keyword  Keyphrase.
 * @return bool
 */
function spokora_text_contains_keyword( $haystack, $keyword ) {
	$keyword = trim( (string) $keyword );
	if ( $keyword === '' ) {
		return false;
	}
	$text = strtolower( wp_strip_all_tags( (string) $haystack ) );
	$text = preg_replace( '/\s+/u', ' ', $text );
	if ( false !== strpos( $text, strtolower( $keyword ) ) ) {
		return true;
	}
	$parts = preg_split( '/\s+/u', strtolower( $keyword ), -1, PREG_SPLIT_NO_EMPTY );
	foreach ( $parts as $part ) {
		if ( false === strpos( $text, $part ) ) {
			return false;
		}
	}
	return ! empty( $parts );
}

/**
 * @param int   $post_id   Post ID.
 * @param array $overrides Optional focus_keyword, additional_keywords.
 * @return array{primary: string, additional: string[]}
 */
function spokora_post_keywords_for_scoring( $post_id, array $overrides = array() ) {
	if ( isset( $overrides['focus_keyword'] ) || isset( $overrides['additional_keywords'] ) ) {
		$primary = isset( $overrides['focus_keyword'] )
			? trim( sanitize_text_field( (string) $overrides['focus_keyword'] ) )
			: '';
		$additional = isset( $overrides['additional_keywords'] )
			? spokora_parse_keyword_input( $overrides['additional_keywords'] )
			: array();
		if ( $primary !== '' ) {
			$additional = array_values(
				array_filter(
					$additional,
					static function ( $kw ) use ( $primary ) {
						return $kw !== $primary;
					}
				)
			);
		}
		return array(
			'primary'    => $primary,
			'additional' => array_slice( $additional, 0, max( 0, spokora_post_keyword_limit() - 1 ) ),
		);
	}
	return spokora_get_post_keywords( $post_id );
}

/**
 * Persist keywords from classic editor POST.
 *
 * @param int $post_id Post ID.
 * @return array{primary: string, additional: string[], all: string[]}
 */
function spokora_save_post_keywords_from_request( $post_id ) {
	$nonce = isset( $_POST['spokora_seo_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spokora_seo_meta_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'spokora_seo_save_meta' ) ) {
		return spokora_get_post_keywords( $post_id );
	}
	$primary = isset( $_POST['spokora_focus_keyword'] )
		? trim( sanitize_text_field( wp_unslash( $_POST['spokora_focus_keyword'] ) ) )
		: '';

	$additional_raw = isset( $_POST['spokora_additional_keywords'] )
		? sanitize_textarea_field( wp_unslash( $_POST['spokora_additional_keywords'] ) )
		: '';
	$additional = spokora_parse_keyword_input( $additional_raw );
	$additional = array_values(
		array_filter(
			$additional,
			static function ( $kw ) use ( $primary ) {
				return $primary === '' || $kw !== $primary;
			}
		)
	);
	$additional = array_slice( $additional, 0, max( 0, spokora_post_keyword_limit() - 1 ) );

	if ( $primary !== '' ) {
		update_post_meta( $post_id, '_spokora_focus_keyword', $primary );
	} else {
		delete_post_meta( $post_id, '_spokora_focus_keyword' );
	}

	if ( ! empty( $additional ) ) {
		update_post_meta( $post_id, '_spokora_additional_keywords', wp_json_encode( $additional ) );
	} else {
		delete_post_meta( $post_id, '_spokora_additional_keywords' );
	}

	$all = array();
	if ( $primary !== '' ) {
		$all[] = $primary;
	}
	foreach ( $additional as $kw ) {
		if ( ! in_array( $kw, $all, true ) ) {
			$all[] = $kw;
		}
	}

	return array(
		'primary'    => $primary,
		'additional' => $additional,
		'all'        => $all,
	);
}

/**
 * Editor fields for primary + additional keyphrases.
 *
 * @param WP_Post $post Post.
 */
function spokora_render_post_keyword_fields( $post ) {
	$bundle     = spokora_get_post_keywords( $post->ID );
	$primary    = $bundle['primary'];
	$additional = implode( "\n", $bundle['additional'] );
	$limit      = spokora_post_keyword_limit();

	echo '<div class="spokora-keywords-panel" style="margin-bottom:16px;">';
	echo '<h4 style="margin:0 0 8px;">' . esc_html__( 'Keyphrases', 'spokora-seo-toolkit' ) . '</h4>';
	echo '<p class="description" style="margin:0 0 10px;">' . esc_html(
		sprintf(
			/* translators: %d: max keyphrases */
			__( 'Primary keyphrase drives on-page checks. Add up to %d total (one per line).', 'spokora-seo-toolkit' ),
			$limit
		)
	) . '</p>';
	echo '<label for="spokora_focus_keyword" style="font-weight:bold;display:block;">' . esc_html__( 'Primary keyphrase', 'spokora-seo-toolkit' ) . '</label>';
	echo '<input type="text" id="spokora_focus_keyword" name="spokora_focus_keyword" value="' . esc_attr( $primary ) . '" style="width:100%;padding:8px;margin-top:5px;" placeholder="' . esc_attr__( 'e.g. cannabis flower tacoma', 'spokora-seo-toolkit' ) . '" />';
	echo '<label for="spokora_additional_keywords" style="font-weight:bold;display:block;margin-top:12px;">' . esc_html__( 'Additional keyphrases', 'spokora-seo-toolkit' ) . '</label>';
	echo '<textarea id="spokora_additional_keywords" name="spokora_additional_keywords" rows="3" style="width:100%;padding:8px;margin-top:5px;font-family:inherit;" placeholder="' . esc_attr__( 'One keyphrase per line', 'spokora-seo-toolkit' ) . '">' . esc_textarea( $additional ) . '</textarea>';
	echo '</div>';
}

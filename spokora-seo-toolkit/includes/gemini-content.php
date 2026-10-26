<?php
/**
 * Resolve post text for Gemini (editor, bundled HTML, screen-map, fallbacks).
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta key storing bundled screen slug (TOF2 and compatible themes).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function spokora_bundled_slug_meta_key( $post_id = 0 ) {
	$key = '_tof2_bundled_slug';
	if ( defined( 'TOF2_BUNDLED_SLUG_META' ) ) {
		$key = TOF2_BUNDLED_SLUG_META;
	}
	return apply_filters( 'spokora_bundled_slug_meta_key', $key, $post_id );
}

/**
 * Whether editor content is empty or a useless placeholder.
 *
 * @param string $content Raw editor HTML/text.
 */
function spokora_is_editor_content_placeholder( $content ) {
	$text = trim( wp_strip_all_tags( (string) $content ) );
	if ( $text === '' ) {
		return true;
	}
	if ( strlen( $text ) < 40 ) {
		return true;
	}
	return (bool) apply_filters( 'spokora_is_editor_content_placeholder', false, $content, $text );
}

/**
 * Bundled slug for a post, if any.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function spokora_get_bundled_slug_for_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}
	$meta = trim( (string) get_post_meta( $post_id, spokora_bundled_slug_meta_key( $post_id ), true ) );
	if ( $meta !== '' ) {
		return sanitize_title( $meta );
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	return sanitize_title( $post->post_name );
}

/**
 * Normalize whitespace for AI prompts.
 *
 * @param string $text Input text.
 * @return string
 */
function spokora_normalize_ai_text( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = wp_strip_all_tags( $text );
	$text = preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
	$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;
	return trim( $text );
}

/**
 * Read bundled HTML from active theme (TOF2-style paths).
 *
 * @param string $slug Screen slug.
 * @return string Normalized text or empty.
 */
function spokora_read_bundled_html_text( $slug ) {
	$slug = sanitize_title( $slug );
	if ( $slug === '' ) {
		return '';
	}
	$candidates = apply_filters(
		'spokora_bundled_html_paths',
		array(
			get_template_directory() . '/assets/bundled/' . $slug . '.html',
			get_stylesheet_directory() . '/assets/bundled/' . $slug . '.html',
		),
		$slug
	);
	foreach ( $candidates as $path ) {
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			continue;
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $raw ) || $raw === '' ) {
			continue;
		}
		return spokora_normalize_ai_text( $raw );
	}
	return '';
}

/**
 * Load a screen-map entry by slug (theme helper or JSON file).
 *
 * @param string $slug Screen slug.
 * @return array<string, mixed>|null
 */
function spokora_get_screen_map_entry( $slug ) {
	$slug = sanitize_title( $slug );
	if ( $slug === '' ) {
		return null;
	}
	if ( function_exists( 'tof2_get_screen_by_slug' ) ) {
		$row = tof2_get_screen_by_slug( $slug );
		return is_array( $row ) ? $row : null;
	}
	$map_paths = apply_filters(
		'spokora_screen_map_paths',
		array(
			get_template_directory() . '/inc/data/screen-map.json',
			get_template_directory() . '/assets/data/screen-map.json',
			get_stylesheet_directory() . '/inc/data/screen-map.json',
		),
		$slug
	);
	foreach ( $map_paths as $path ) {
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			continue;
		}
		$json = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		$screens = isset( $data['screens'] ) && is_array( $data['screens'] ) ? $data['screens'] : $data;
		foreach ( $screens as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_slug = isset( $row['slug'] ) ? sanitize_title( (string) $row['slug'] ) : '';
			if ( $row_slug === $slug ) {
				return $row;
			}
		}
	}
	return null;
}

/**
 * Build text from a screen-map row.
 *
 * @param array<string, mixed> $row Screen row.
 * @return string
 */
function spokora_text_from_screen_map_row( array $row ) {
	$parts = array();
	if ( ! empty( $row['display_title'] ) ) {
		$parts[] = 'Title: ' . (string) $row['display_title'];
	}
	if ( ! empty( $row['meta_description'] ) ) {
		$parts[] = 'Meta description: ' . (string) $row['meta_description'];
	}
	if ( isset( $row['seo'] ) && is_array( $row['seo'] ) && ! empty( $row['seo']['meta_description'] ) ) {
		$parts[] = 'Meta description: ' . (string) $row['seo']['meta_description'];
	}
	if ( ! empty( $row['summary'] ) ) {
		$parts[] = 'Summary: ' . (string) $row['summary'];
	}
	return spokora_normalize_ai_text( implode( "\n", $parts ) );
}

/**
 * Whether an alternate (non-editor) content source exists for this post.
 *
 * @param int $post_id Post ID.
 */
function spokora_has_alternate_content_source( $post_id ) {
	$resolved = spokora_resolve_ai_content( $post_id, '' );
	return is_array( $resolved ) && ! empty( $resolved['text'] ) && 'editor' !== ( $resolved['source'] ?? '' );
}

/**
 * Resolve the best text source for Gemini prompts.
 *
 * @param int    $post_id        Post ID.
 * @param string $editor_content Optional live editor content from the browser.
 * @return array{text: string, source: string, label: string}|WP_Error
 */
function spokora_resolve_ai_content( $post_id, $editor_content = '' ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return new WP_Error( 'spokora_ai_no_post', __( 'Invalid post.', 'spokora-seo-toolkit' ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'spokora_ai_forbidden', __( 'You cannot edit this post.', 'spokora-seo-toolkit' ) );
	}

	if ( ! spokora_is_editor_content_placeholder( $editor_content ) ) {
		return array(
			'text'   => spokora_normalize_ai_text( $editor_content ),
			'source' => 'editor',
			'label'  => __( 'WordPress editor', 'spokora-seo-toolkit' ),
		);
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'spokora_ai_no_post', __( 'Post not found.', 'spokora-seo-toolkit' ) );
	}

	$slug = spokora_get_bundled_slug_for_post( $post_id );
	if ( $slug !== '' ) {
		$html_text = spokora_read_bundled_html_text( $slug );
		if ( $html_text !== '' ) {
			return array(
				'text'   => $html_text,
				'source' => 'bundled_html',
				'label'  => sprintf(
					/* translators: %s: bundled file slug */
					__( 'Bundled HTML (%s)', 'spokora-seo-toolkit' ),
					$slug
				),
			);
		}
		$map_row = spokora_get_screen_map_entry( $slug );
		if ( is_array( $map_row ) ) {
			$map_text = spokora_text_from_screen_map_row( $map_row );
			if ( $map_text !== '' ) {
				return array(
					'text'   => $map_text,
					'source' => 'screen_map',
					'label'  => sprintf(
						/* translators: %s: screen slug */
						__( 'Screen map (%s)', 'spokora-seo-toolkit' ),
						$slug
					),
				);
			}
		}
	}

	$excerpt = trim( (string) $post->post_excerpt );
	if ( $excerpt !== '' ) {
		return array(
			'text'   => spokora_normalize_ai_text( $excerpt ),
			'source' => 'excerpt',
			'label'  => __( 'Post excerpt', 'spokora-seo-toolkit' ),
		);
	}

	$body = spokora_normalize_ai_text( $post->post_content );
	if ( $body !== '' ) {
		return array(
			'text'   => $body,
			'source' => 'post_content',
			'label'  => __( 'Saved post content', 'spokora-seo-toolkit' ),
		);
	}

	$filtered = apply_filters( 'spokora_resolve_ai_content', null, $post_id, $editor_content );
	if ( is_array( $filtered ) && ! empty( $filtered['text'] ) ) {
		return array(
			'text'   => spokora_normalize_ai_text( (string) $filtered['text'] ),
			'source' => isset( $filtered['source'] ) ? (string) $filtered['source'] : 'filter',
			'label'  => isset( $filtered['label'] ) ? (string) $filtered['label'] : __( 'Custom source', 'spokora-seo-toolkit' ),
		);
	}

	return new WP_Error(
		'spokora_ai_no_content',
		__( 'No content found. Add editor text, a bundled HTML file, or an excerpt.', 'spokora-seo-toolkit' )
	);
}

/**
 * Cap text length for Gemini prompts.
 *
 * @param string $text   Input text.
 * @param int    $limit  Character limit.
 * @return string
 */
function spokora_cap_ai_text( $text, $limit = 12000 ) {
	$text = (string) $text;
	if ( strlen( $text ) <= $limit ) {
		return $text;
	}
	return substr( $text, 0, $limit ) . '…';
}

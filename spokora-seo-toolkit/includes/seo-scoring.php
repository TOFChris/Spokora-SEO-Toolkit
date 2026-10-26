<?php
/**
 * On-page SEO + AI readiness scoring (rule-based).
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that receive scores.
 *
 * @return string[]
 */
function spokora_scoring_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	if ( ! $types ) {
		$types = array( 'post', 'page' );
	}
	return apply_filters( 'spokora_scoring_post_types', array_values( $types ) );
}

/**
 * Post types that get Spokora editor metaboxes (SEO, AI docs, social, drafts, etc.).
 *
 * @return string[]
 */
function spokora_editor_metabox_post_types() {
	$types = spokora_scoring_post_types();
	foreach ( array( 'spokora_show', 'spokora_episode' ) as $pt ) {
		if ( post_type_exists( $pt ) && ! in_array( $pt, $types, true ) ) {
			$types[] = $pt;
		}
	}
	return apply_filters( 'spokora_editor_metabox_post_types', array_values( $types ) );
}

/**
 * Post types the Write a draft screen may create (needs title + editor support).
 *
 * @return string[]
 */
function spokora_draftable_post_types() {
	$out = array();
	foreach ( spokora_editor_metabox_post_types() as $type ) {
		$obj = get_post_type_object( $type );
		if ( ! $obj || ! post_type_supports( $type, 'editor' ) ) {
			continue;
		}
		$out[] = $type;
	}
	return apply_filters( 'spokora_draftable_post_types', $out );
}

/**
 * Resolve text used for content-length / heading checks.
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Optional title, description, content overrides from editor.
 * @return array{text: string, source: string, label: string}
 */
function spokora_get_scoring_content( $post_id, array $overrides = array() ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array(
			'text'   => '',
			'source' => 'none',
			'label'  => __( 'No content', 'spokora-seo-toolkit' ),
		);
	}

	$editor = isset( $overrides['content'] ) ? (string) $overrides['content'] : (string) $post->post_content;
	if ( ! function_exists( 'spokora_resolve_ai_content' ) ) {
		$body = wp_strip_all_tags( $editor !== '' ? $editor : (string) $post->post_content );
		return array(
			'text'   => trim( $body ),
			'source' => 'post_content',
			'label'  => __( 'Saved post content', 'spokora-seo-toolkit' ),
		);
	}
	$resolved = spokora_resolve_ai_content( $post_id, $editor );
	if ( is_wp_error( $resolved ) ) {
		$body = function_exists( 'spokora_normalize_ai_text' )
			? spokora_normalize_ai_text( $post->post_content )
			: wp_strip_all_tags( (string) $post->post_content );
		if ( $body === '' && $post->post_excerpt !== '' ) {
			$body = function_exists( 'spokora_normalize_ai_text' )
				? spokora_normalize_ai_text( $post->post_excerpt )
				: wp_strip_all_tags( (string) $post->post_excerpt );
		}
		return array(
			'text'   => $body,
			'source' => 'post_content',
			'label'  => __( 'Saved post content', 'spokora-seo-toolkit' ),
		);
	}

	return array(
		'text'   => (string) $resolved['text'],
		'source' => (string) $resolved['source'],
		'label'  => (string) $resolved['label'],
	);
}

/**
 * Build a single check row.
 *
 * @param string $id      Check id.
 * @param string $label   Label.
 * @param string $status  pass|warn|fail|na.
 * @param string $message Detail message.
 * @param int    $weight  Weight toward score.
 * @param int    $earned  Points earned.
 * @return array<string, mixed>
 */
function spokora_score_check( $id, $label, $status, $message, $weight, $earned ) {
	return array(
		'id'      => $id,
		'label'   => $label,
		'status'  => $status,
		'message' => $message,
		'weight'  => (int) $weight,
		'earned'  => (int) $earned,
	);
}

/**
 * Points for a check status.
 *
 * @param string $status pass|warn|fail|na.
 * @param int    $weight Max weight.
 * @return int
 */
function spokora_score_points_for_status( $status, $weight ) {
	if ( 'pass' === $status ) {
		return $weight;
	}
	if ( 'warn' === $status ) {
		return (int) max( 0, round( $weight * 0.5 ) );
	}
	if ( 'na' === $status ) {
		return $weight;
	}
	return 0;
}

/**
 * Effective SEO title for scoring.
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Overrides.
 * @return string
 */
function spokora_scoring_effective_title( $post_id, array $overrides = array() ) {
	if ( isset( $overrides['title'] ) ) {
		return trim( (string) $overrides['title'] );
	}
	$custom = trim( (string) get_post_meta( $post_id, '_spokora_seo_title', true ) );
	if ( $custom !== '' ) {
		return $custom;
	}
	$post = get_post( $post_id );
	return $post ? trim( (string) $post->post_title ) : '';
}

/**
 * Effective meta description for scoring.
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Overrides.
 * @return string
 */
function spokora_scoring_effective_description( $post_id, array $overrides = array() ) {
	if ( isset( $overrides['description'] ) ) {
		return trim( (string) $overrides['description'] );
	}
	$custom = trim( (string) get_post_meta( $post_id, '_spokora_seo_desc', true ) );
	if ( $custom !== '' ) {
		return $custom;
	}
	$post = get_post( $post_id );
	if ( $post && trim( (string) $post->post_excerpt ) !== '' ) {
		return trim( (string) $post->post_excerpt );
	}
	$opts = get_option( 'spokora_options', array() );
	if ( is_array( $opts ) && ! empty( $opts['global_desc'] ) ) {
		return trim( (string) $opts['global_desc'] );
	}
	return '';
}

/**
 * Search SEO score (0–100) + checklist.
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Optional title, description, content.
 * @return array{score: int, status: string, checks: array, content_source: array, excluded: bool}
 */
function spokora_calculate_search_seo_score( $post_id, array $overrides = array() ) {
	$post_id = (int) $post_id;
	$excluded = get_post_meta( $post_id, '_spokora_seo_score_exclude', true ) === '1';
	if ( $excluded ) {
		return array(
			'score'           => 100,
			'status'          => 'na',
			'checks'          => array(
				spokora_score_check(
					'excluded',
					__( 'Excluded from scoring', 'spokora-seo-toolkit' ),
					'na',
					__( 'This page is excluded from SEO scoring.', 'spokora-seo-toolkit' ),
					100,
					100
				),
			),
			'content_source'  => array( 'source' => 'excluded', 'label' => '' ),
			'excluded'        => true,
		);
	}

	$title = spokora_scoring_effective_title( $post_id, $overrides );
	$desc  = spokora_scoring_effective_description( $post_id, $overrides );
	$content_bundle = spokora_get_scoring_content( $post_id, $overrides );
	$content_text   = $content_bundle['text'];
	$word_count     = $content_text !== '' ? str_word_count( $content_text ) : 0;

	$checks = array();

	// Title presence (15).
	$title_len = strlen( $title );
	if ( $title === '' ) {
		$checks[] = spokora_score_check(
			'title_present',
			__( 'SEO title', 'spokora-seo-toolkit' ),
			'fail',
			__( 'Add a custom SEO title or ensure the page title is set.', 'spokora-seo-toolkit' ),
			15,
			0
		);
	} elseif ( $title_len >= 50 && $title_len <= 60 ) {
		$checks[] = spokora_score_check(
			'title_present',
			__( 'SEO title', 'spokora-seo-toolkit' ),
			'pass',
			sprintf(
				/* translators: %d: character count */
				__( 'Good length (%d characters).', 'spokora-seo-toolkit' ),
				$title_len
			),
			15,
			15
		);
	} elseif ( $title_len >= 40 && $title_len <= 70 ) {
		$checks[] = spokora_score_check(
			'title_present',
			__( 'SEO title', 'spokora-seo-toolkit' ),
			'warn',
			sprintf(
				/* translators: %1$d: title character count */
				__( 'Acceptable length (%1$d chars). Ideal is 50–60.', 'spokora-seo-toolkit' ),
				$title_len
			),
			15,
			spokora_score_points_for_status( 'warn', 15 )
		);
	} else {
		$checks[] = spokora_score_check(
			'title_present',
			__( 'SEO title', 'spokora-seo-toolkit' ),
			'warn',
			sprintf(
				/* translators: %1$d: title character count */
				__( 'Length is %1$d characters. Aim for 50–60.', 'spokora-seo-toolkit' ),
				$title_len
			),
			15,
			spokora_score_points_for_status( 'warn', 15 )
		);
	}

	// Meta description (20).
	$desc_len = strlen( $desc );
	if ( $desc === '' ) {
		$checks[] = spokora_score_check(
			'meta_desc',
			__( 'Meta description', 'spokora-seo-toolkit' ),
			'fail',
			__( 'Add a meta description (120–160 characters).', 'spokora-seo-toolkit' ),
			20,
			0
		);
	} elseif ( $desc_len >= 120 && $desc_len <= 160 ) {
		$checks[] = spokora_score_check(
			'meta_desc',
			__( 'Meta description', 'spokora-seo-toolkit' ),
			'pass',
			sprintf(
				/* translators: %d: meta description character count */
				__( 'Good length (%d characters).', 'spokora-seo-toolkit' ),
				$desc_len
			),
			20,
			20
		);
	} elseif ( $desc_len >= 90 && $desc_len <= 180 ) {
		$checks[] = spokora_score_check(
			'meta_desc',
			__( 'Meta description', 'spokora-seo-toolkit' ),
			'warn',
			sprintf(
				/* translators: %1$d: meta description character count */
				__( '%1$d characters — close; aim for 120–160.', 'spokora-seo-toolkit' ),
				$desc_len
			),
			20,
			spokora_score_points_for_status( 'warn', 20 )
		);
	} else {
		$checks[] = spokora_score_check(
			'meta_desc',
			__( 'Meta description', 'spokora-seo-toolkit' ),
			'warn',
			sprintf(
				/* translators: %1$d: meta description character count */
				__( '%1$d characters — adjust toward 120–160.', 'spokora-seo-toolkit' ),
				$desc_len
			),
			20,
			spokora_score_points_for_status( 'warn', 20 )
		);
	}

	// Content depth (25) — uses resolved/bundled text.
	$min_words = (int) apply_filters( 'spokora_seo_min_word_count', 300, $post_id );
	$warn_words = (int) apply_filters( 'spokora_seo_warn_word_count', 150, $post_id );
	if ( $word_count >= $min_words ) {
		$checks[] = spokora_score_check(
			'content_depth',
			__( 'Content depth', 'spokora-seo-toolkit' ),
			'pass',
			sprintf(
				/* translators: 1: word count, 2: content source label */
				__( '%1$d words (%2$s).', 'spokora-seo-toolkit' ),
				$word_count,
				$content_bundle['label']
			),
			25,
			25
		);
	} elseif ( $word_count >= $warn_words ) {
		$checks[] = spokora_score_check(
			'content_depth',
			__( 'Content depth', 'spokora-seo-toolkit' ),
			'warn',
			sprintf(
				/* translators: %1$d: word count in post body */
				__( '%1$d words — thin for SEO. Add more substantive copy.', 'spokora-seo-toolkit' ),
				$word_count
			),
			25,
			spokora_score_points_for_status( 'warn', 25 )
		);
	} else {
		$checks[] = spokora_score_check(
			'content_depth',
			__( 'Content depth', 'spokora-seo-toolkit' ),
			'fail',
			$word_count > 0
				? sprintf(
					/* translators: %1$d: word count in post body */
					__( 'Only %1$d words detected.', 'spokora-seo-toolkit' ),
					$word_count
				)
				: __( 'No indexable body text found.', 'spokora-seo-toolkit' ),
			25,
			0
		);
	}

	// Heading structure (10).
	$has_h1 = (bool) preg_match( '/<h1[\s>]/i', $content_text );
	$post   = get_post( $post_id );
	if ( $has_h1 || ( $post && trim( (string) $post->post_title ) !== '' ) ) {
		$checks[] = spokora_score_check(
			'headings',
			__( 'Title / H1', 'spokora-seo-toolkit' ),
			'pass',
			$has_h1 ? __( 'H1 found in content.', 'spokora-seo-toolkit' ) : __( 'Page title serves as primary heading.', 'spokora-seo-toolkit' ),
			10,
			10
		);
	} else {
		$checks[] = spokora_score_check(
			'headings',
			__( 'Title / H1', 'spokora-seo-toolkit' ),
			'warn',
			__( 'Add an H1 or clear page title.', 'spokora-seo-toolkit' ),
			10,
			spokora_score_points_for_status( 'warn', 10 )
		);
	}

	// Featured image + alt (10).
	$thumb_id = $post ? (int) get_post_thumbnail_id( $post ) : 0;
	if ( $thumb_id > 0 ) {
		$alt = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
		if ( $alt !== '' ) {
			$checks[] = spokora_score_check(
				'featured_image',
				__( 'Featured image', 'spokora-seo-toolkit' ),
				'pass',
				__( 'Featured image has alt text.', 'spokora-seo-toolkit' ),
				10,
				10
			);
		} else {
			$checks[] = spokora_score_check(
				'featured_image',
				__( 'Featured image', 'spokora-seo-toolkit' ),
				'warn',
				__( 'Featured image missing alt text.', 'spokora-seo-toolkit' ),
				10,
				spokora_score_points_for_status( 'warn', 10 )
			);
		}
	} else {
		$checks[] = spokora_score_check(
			'featured_image',
			__( 'Featured image', 'spokora-seo-toolkit' ),
			'warn',
			__( 'No featured image set.', 'spokora-seo-toolkit' ),
			10,
			spokora_score_points_for_status( 'warn', 10 )
		);
	}

	// Focus keyphrases (15) — primary + additional, Yoast-style.
	$kw_bundle = function_exists( 'spokora_post_keywords_for_scoring' )
		? spokora_post_keywords_for_scoring( $post_id, $overrides )
		: array( 'primary' => '', 'additional' => array() );
	$primary_kw = $kw_bundle['primary'];
	$post       = get_post( $post_id );
	$slug       = $post ? (string) $post->post_name : '';

	if ( $primary_kw === '' ) {
		$checks[] = spokora_score_check(
			'focus_keyphrase',
			__( 'Primary keyphrase', 'spokora-seo-toolkit' ),
			'na',
			__( 'Add a primary keyphrase to score keyphrase usage.', 'spokora-seo-toolkit' ),
			15,
			15
		);
	} else {
		$in_title = spokora_text_contains_keyword( $title, $primary_kw );
		$in_desc  = spokora_text_contains_keyword( $desc, $primary_kw );
		$in_body  = spokora_text_contains_keyword( $content_text, $primary_kw );
		$in_slug  = $slug !== '' && spokora_text_contains_keyword( str_replace( '-', ' ', $slug ), $primary_kw );

		if ( $in_title ) {
			$checks[] = spokora_score_check(
				'focus_in_title',
				__( 'Keyphrase in SEO title', 'spokora-seo-toolkit' ),
				'pass',
				__( 'Primary keyphrase appears in the SEO title.', 'spokora-seo-toolkit' ),
				5,
				5
			);
		} else {
			$checks[] = spokora_score_check(
				'focus_in_title',
				__( 'Keyphrase in SEO title', 'spokora-seo-toolkit' ),
				'warn',
				__( 'Use the primary keyphrase in the SEO title.', 'spokora-seo-toolkit' ),
				5,
				spokora_score_points_for_status( 'warn', 5 )
			);
		}

		if ( $in_desc ) {
			$checks[] = spokora_score_check(
				'focus_in_desc',
				__( 'Keyphrase in meta description', 'spokora-seo-toolkit' ),
				'pass',
				__( 'Primary keyphrase appears in the meta description.', 'spokora-seo-toolkit' ),
				5,
				5
			);
		} else {
			$checks[] = spokora_score_check(
				'focus_in_desc',
				__( 'Keyphrase in meta description', 'spokora-seo-toolkit' ),
				'warn',
				__( 'Use the primary keyphrase in the meta description.', 'spokora-seo-toolkit' ),
				5,
				spokora_score_points_for_status( 'warn', 5 )
			);
		}

		if ( $in_body || $in_slug ) {
			$checks[] = spokora_score_check(
				'focus_in_content',
				__( 'Keyphrase in content', 'spokora-seo-toolkit' ),
				'pass',
				$in_body
					? __( 'Primary keyphrase appears in the body.', 'spokora-seo-toolkit' )
					: __( 'Primary keyphrase appears in the URL slug.', 'spokora-seo-toolkit' ),
				5,
				5
			);
		} else {
			$checks[] = spokora_score_check(
				'focus_in_content',
				__( 'Keyphrase in content', 'spokora-seo-toolkit' ),
				'fail',
				__( 'Use the primary keyphrase in the body or slug.', 'spokora-seo-toolkit' ),
				5,
				0
			);
		}

		if ( ! empty( $kw_bundle['additional'] ) ) {
			$missing = array();
			foreach ( $kw_bundle['additional'] as $extra_kw ) {
				if ( ! spokora_text_contains_keyword( $content_text, $extra_kw ) && ! spokora_text_contains_keyword( $title . ' ' . $desc, $extra_kw ) ) {
					$missing[] = $extra_kw;
				}
			}
			if ( empty( $missing ) ) {
				$checks[] = spokora_score_check(
					'additional_keyphrases',
					__( 'Additional keyphrases', 'spokora-seo-toolkit' ),
					'pass',
					__( 'All additional keyphrases appear in title, description, or body.', 'spokora-seo-toolkit' ),
					0,
					0
				);
			} else {
				$checks[] = spokora_score_check(
					'additional_keyphrases',
					__( 'Additional keyphrases', 'spokora-seo-toolkit' ),
					'warn',
					sprintf(
						/* translators: %s: comma-separated keyphrases */
						__( 'Missing from content: %s', 'spokora-seo-toolkit' ),
						implode( ', ', array_slice( $missing, 0, 3 ) )
					),
					0,
					0
				);
			}
		}
	}

	// Schema coverage (10).
	$custom_schema = trim( (string) get_post_meta( $post_id, '_spokora_custom_schema', true ) );
	$opts          = get_option( 'spokora_options', array() );
	$global_schema = is_array( $opts ) ? trim( (string) ( $opts['global_schema_json'] ?? '' ) ) : '';
	$org_name      = is_array( $opts ) ? trim( (string) ( $opts['org_name'] ?? '' ) ) : '';
	if ( $custom_schema !== '' || $global_schema !== '' || $org_name !== '' ) {
		$checks[] = spokora_score_check(
			'schema',
			__( 'Structured data', 'spokora-seo-toolkit' ),
			'pass',
			$custom_schema !== ''
				? __( 'Custom JSON-LD on this page.', 'spokora-seo-toolkit' )
				: __( 'Global entity schema configured.', 'spokora-seo-toolkit' ),
			10,
			10
		);
	} else {
		$checks[] = spokora_score_check(
			'schema',
			__( 'Structured data', 'spokora-seo-toolkit' ),
			'warn',
			__( 'Configure global schema or per-page JSON-LD.', 'spokora-seo-toolkit' ),
			10,
			spokora_score_points_for_status( 'warn', 10 )
		);
	}

	// Front-end SEO output enabled (10).
	if ( function_exists( 'spokora_should_output_seo' ) && spokora_should_output_seo() ) {
		$checks[] = spokora_score_check(
			'spokora_output',
			__( 'Spokora SEO output', 'spokora-seo-toolkit' ),
			'pass',
			__( 'Spokora will output meta on the front end.', 'spokora-seo-toolkit' ),
			10,
			10
		);
	} else {
		$checks[] = spokora_score_check(
			'spokora_output',
			__( 'Spokora SEO output', 'spokora-seo-toolkit' ),
			'na',
			__( 'Theme owns front-end SEO — meta here is for reference/import only.', 'spokora-seo-toolkit' ),
			10,
			10
		);
	}

	$score = 0;
	foreach ( $checks as $check ) {
		$score += (int) $check['earned'];
	}
	$score = min( 100, max( 0, $score ) );

	return array(
		'score'          => $score,
		'status'         => spokora_score_status_from_number( $score ),
		'checks'         => $checks,
		'content_source' => $content_bundle,
		'excluded'       => false,
		'serp'           => array(
			'title'       => $title !== '' ? $title : ( $post ? get_the_title( $post ) : '' ),
			'description' => $desc,
			'url'         => $post ? get_permalink( $post ) : home_url( '/' ),
		),
	);
}

/**
 * AI readiness score (0–100).
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Optional llms fields from editor.
 * @return array{score: int, status: string, checks: array}
 */
function spokora_calculate_ai_readiness_score( $post_id, array $overrides = array() ) {
	$post_id = (int) $post_id;
	if ( ! function_exists( 'spokora_is_module_enabled' ) || ! spokora_is_module_enabled( 'llms' ) ) {
		return array(
			'score'   => 0,
			'status'  => 'na',
			'checks'  => array(
				spokora_score_check(
					'llms_module',
					__( 'LLMS module', 'spokora-seo-toolkit' ),
					'na',
					__( 'Enable the LLMS module to score AI readiness.', 'spokora-seo-toolkit' ),
					100,
					0
				),
			),
			'disabled' => true,
		);
	}

	$public  = isset( $overrides['llms_public'] ) ? ! empty( $overrides['llms_public'] ) : get_post_meta( $post_id, '_spokora_llms_public', true ) === '1';
	$summary = isset( $overrides['llms_summary'] ) ? trim( (string) $overrides['llms_summary'] ) : trim( (string) get_post_meta( $post_id, '_spokora_llms_summary', true ) );
	$md      = isset( $overrides['llms_md'] ) ? trim( (string) $overrides['llms_md'] ) : trim( (string) get_post_meta( $post_id, '_spokora_llms_md', true ) );

	$checks = array();

	if ( $public ) {
		$checks[] = spokora_score_check(
			'llms_public',
			__( 'Public AI document', 'spokora-seo-toolkit' ),
			'pass',
			__( 'Enabled — eligible for /llms.txt and /ai-docs/{slug}.md', 'spokora-seo-toolkit' ),
			25,
			25
		);
	} else {
		$checks[] = spokora_score_check(
			'llms_public',
			__( 'Public AI document', 'spokora-seo-toolkit' ),
			'fail',
			__( 'Enable Public AI document for crawler-facing docs.', 'spokora-seo-toolkit' ),
			25,
			0
		);
	}

	if ( $summary !== '' && strlen( $summary ) <= 200 ) {
		$checks[] = spokora_score_check(
			'llms_summary',
			__( 'LLMS summary line', 'spokora-seo-toolkit' ),
			'pass',
			__( 'One-line summary is set for /llms.txt.', 'spokora-seo-toolkit' ),
			25,
			25
		);
	} elseif ( $summary !== '' ) {
		$checks[] = spokora_score_check(
			'llms_summary',
			__( 'LLMS summary line', 'spokora-seo-toolkit' ),
			'warn',
			__( 'Summary is long — keep hub lines under ~200 characters.', 'spokora-seo-toolkit' ),
			25,
			spokora_score_points_for_status( 'warn', 25 )
		);
	} else {
		$checks[] = spokora_score_check(
			'llms_summary',
			__( 'LLMS summary line', 'spokora-seo-toolkit' ),
			'fail',
			__( 'Add a one-line summary for the llms.txt index.', 'spokora-seo-toolkit' ),
			25,
			0
		);
	}

	if ( strlen( $md ) >= 200 ) {
		$checks[] = spokora_score_check(
			'llms_md',
			__( 'AI doc body', 'spokora-seo-toolkit' ),
			'pass',
			__( 'Markdown body is substantial.', 'spokora-seo-toolkit' ),
			30,
			30
		);
	} elseif ( strlen( $md ) >= 80 ) {
		$checks[] = spokora_score_check(
			'llms_md',
			__( 'AI doc body', 'spokora-seo-toolkit' ),
			'warn',
			__( 'Markdown body is short — expand for assistants.', 'spokora-seo-toolkit' ),
			30,
			spokora_score_points_for_status( 'warn', 30 )
		);
	} else {
		$checks[] = spokora_score_check(
			'llms_md',
			__( 'AI doc body', 'spokora-seo-toolkit' ),
			'fail',
			__( 'Add markdown for /ai-docs/{slug}.md or use Generate LLMS doc.', 'spokora-seo-toolkit' ),
			30,
			0
		);
	}

	$opts     = get_option( 'spokora_options', array() );
	$preamble = is_array( $opts ) ? trim( (string) ( $opts['llms_preamble'] ?? '' ) ) : '';
	if ( $preamble !== '' ) {
		$checks[] = spokora_score_check(
			'site_preamble',
			__( 'Site llms preamble', 'spokora-seo-toolkit' ),
			'pass',
			__( 'Hub preamble configured in Spokora → LLMS.', 'spokora-seo-toolkit' ),
			10,
			10
		);
	} else {
		$checks[] = spokora_score_check(
			'site_preamble',
			__( 'Site llms preamble', 'spokora-seo-toolkit' ),
			'warn',
			__( 'Optional: add hub preamble under LLMS / AI docs.', 'spokora-seo-toolkit' ),
			10,
			spokora_score_points_for_status( 'warn', 10 )
		);
	}

	$content_bundle = spokora_get_scoring_content( $post_id, $overrides );
	$words          = $content_bundle['text'] !== '' ? str_word_count( $content_bundle['text'] ) : 0;
	if ( $words >= 100 ) {
		$checks[] = spokora_score_check(
			'source_text',
			__( 'Source content', 'spokora-seo-toolkit' ),
			'pass',
			sprintf(
				/* translators: %1$d: word count used for AI docs */
				__( '%1$d words available to derive AI docs.', 'spokora-seo-toolkit' ),
				$words
			),
			10,
			10
		);
	} else {
		$checks[] = spokora_score_check(
			'source_text',
			__( 'Source content', 'spokora-seo-toolkit' ),
			'warn',
			__( 'Little source text — the model may produce thin AI docs.', 'spokora-seo-toolkit' ),
			10,
			spokora_score_points_for_status( 'warn', 10 )
		);
	}

	$score = 0;
	foreach ( $checks as $check ) {
		$score += (int) $check['earned'];
	}
	$score = min( 100, max( 0, $score ) );

	return array(
		'score'    => $score,
		'status'   => spokora_score_status_from_number( $score ),
		'checks'   => $checks,
		'disabled' => false,
	);
}

/**
 * @param int $score Score 0-100.
 * @return string good|ok|poor|na
 */
function spokora_score_status_from_number( $score ) {
	if ( $score >= 80 ) {
		return 'good';
	}
	if ( $score >= 50 ) {
		return 'ok';
	}
	return 'poor';
}

/**
 * Compute both scores and persist post meta.
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Optional field overrides.
 * @return array{search: array, ai: array}
 */
function spokora_compute_and_save_scores( $post_id, array $overrides = array() ) {
	$search = spokora_calculate_search_seo_score( $post_id, $overrides );
	$ai     = spokora_calculate_ai_readiness_score( $post_id, $overrides );

	update_post_meta( $post_id, '_spokora_seo_score', (int) $search['score'] );
	update_post_meta( $post_id, '_spokora_llms_score', (int) $ai['score'] );
	update_post_meta( $post_id, '_spokora_seo_score_updated', time() );

	return array(
		'search' => $search,
		'ai'     => $ai,
	);
}

/**
 * Combined scores for API/JS.
 *
 * @param int   $post_id Post ID.
 * @param array $overrides Overrides.
 * @return array{search: array, ai: array}
 */
function spokora_get_page_scores( $post_id, array $overrides = array() ) {
	static $cache = array();

	$post_id = (int) $post_id;
	$key     = $post_id . ':' . md5( wp_json_encode( $overrides ) );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$cache[ $key ] = array(
		'search' => spokora_calculate_search_seo_score( $post_id, $overrides ),
		'ai'     => spokora_calculate_ai_readiness_score( $post_id, $overrides ),
	);

	return $cache[ $key ];
}

/**
 * Lightweight score snapshot for editor boot (avoids full checklist work on first paint).
 *
 * @param int $post_id Post ID.
 * @return array{search: array{score: int, status: string}, ai: array{score: int, status: string}}
 */
function spokora_get_page_scores_sidebar_boot( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return array(
			'search' => array( 'score' => 0, 'status' => 'na' ),
			'ai'     => array( 'score' => 0, 'status' => 'na' ),
		);
	}

	$seo = (int) get_post_meta( $post_id, '_spokora_seo_score', true );
	$ai  = (int) get_post_meta( $post_id, '_spokora_llms_score', true );

	return array(
		'search' => array(
			'score'  => $seo,
			'status' => spokora_score_status_from_number( $seo ),
		),
		'ai'     => array(
			'score'  => $ai,
			'status' => spokora_score_status_from_number( $ai ),
		),
	);
}

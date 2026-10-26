<?php
/**
 * Fleet llms.txt hub guards — preserve gold preambles on plugin sites.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Whether the hub preamble is locked against accidental clear on save.
 */
function spokora_llms_preamble_is_locked() {
	$opts = get_option( 'spokora_options', array() );
	if ( ! is_array( $opts ) ) {
		return false;
	}

	return isset( $opts['llms_preamble_locked'] ) && (string) $opts['llms_preamble_locked'] === '1';
}

/**
 * Whether to append plugin-generated hub sections (Deep-Dive, brand vocab, Human website).
 * When false, only title, tagline, preamble, and minimal machine links are served.
 */
function spokora_llms_hub_use_generated_sections() {
	$opts = get_option( 'spokora_options', array() );
	if ( ! is_array( $opts ) ) {
		return true;
	}
	if ( isset( $opts['llms_hub_generated_sections'] ) && (string) $opts['llms_hub_generated_sections'] === '0' ) {
		return false;
	}

	return true;
}

/**
 * Merge checkbox flags when a settings form omits unchecked boxes.
 *
 * @param array<string, mixed> $merge Merged options.
 * @param array<string, mixed> $old   Previous options.
 * @param array<string, mixed> $value Submitted options.
 * @return array<string, mixed>
 */
function spokora_llms_merge_option_flags( $merge, $old, $value ) {
	$flags = array( 'llms_preamble_locked', 'llms_hub_generated_sections' );
	foreach ( $flags as $flag ) {
		if ( ! array_key_exists( $flag, $value ) ) {
			if ( isset( $old[ $flag ] ) ) {
				$merge[ $flag ] = $old[ $flag ];
			}
			continue;
		}
		$merge[ $flag ] = (string) $value[ $flag ] === '1' ? '1' : '0';
	}

	return $merge;
}

/**
 * Restore locked preamble / brand vocab if a save would wipe them.
 *
 * @param array<string, mixed> $merge Merged options after sanitize.
 * @param array<string, mixed> $old   Previous options.
 * @return array<string, mixed>
 */
function spokora_llms_apply_preamble_guard( $merge, $old ) {
	if ( ! isset( $merge['llms_preamble_locked'] ) || (string) $merge['llms_preamble_locked'] !== '1' ) {
		return $merge;
	}

	foreach ( array( 'llms_preamble', 'llms_brand_vocab' ) as $key ) {
		$old_val = isset( $old[ $key ] ) ? trim( (string) $old[ $key ] ) : '';
		$new_val = isset( $merge[ $key ] ) ? trim( (string) $merge[ $key ] ) : '';
		if ( $old_val !== '' && $new_val === '' ) {
			$merge[ $key ] = $old[ $key ];
		}
	}

	return $merge;
}

/**
 * Minimal footer when the full hub lives in the preamble (fleet gold copy).
 *
 * @param string[] $out   Hub lines.
 * @param string   $base Site base URL.
 * @return string[]
 */
function spokora_llms_minimal_machine_footer( $out, $base ) {
	$out[] = '';
	$out[] = '## Machine discovery';
	$out[] = '- [Sitemap](' . $base . '/sitemap.xml)';
	$out[] = '- [Robots](' . $base . '/robots.txt)';
	$out[] = '- [LLMS hub](' . $base . '/llms.txt)';

	return $out;
}

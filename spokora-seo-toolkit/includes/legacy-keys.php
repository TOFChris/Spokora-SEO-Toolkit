<?php
/**
 * One-time rename of legacy HW Core option and post-meta keys.
 *
 * Runtime code reads Spokora keys only. This file is the migration boundary.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'spokora_legacy_option_key_map' ) ) {
	/**
	 * @return array<string, string> Old key => current key inside spokora_options.
	 */
	function spokora_legacy_option_key_map() {
		return array(
			'hwc_seo_output'         => 'seo_output',
			'hwc_home_title'         => 'home_title',
			'hwc_home_desc'          => 'home_desc',
			'hwc_org_name'           => 'org_name',
			'hwc_logo_url'           => 'logo_url',
			'hwc_public_email'       => 'public_email',
			'hwc_latitude'           => 'latitude',
			'hwc_longitude'          => 'longitude',
			'hwc_founder_name'       => 'founder_name',
			'hwc_founder_title'      => 'founder_title',
			'hwc_services'           => 'services',
			'hwc_socials'            => 'socials',
			'hwc_global_schema_json' => 'global_schema_json',
		);
	}
}

if ( ! function_exists( 'spokora_rename_legacy_option_keys' ) ) {
	/**
	 * @param mixed $opts Option array.
	 * @return array<string, mixed>
	 */
	function spokora_rename_legacy_option_keys( $opts ) {
		if ( ! is_array( $opts ) ) {
			return array();
		}
		foreach ( spokora_legacy_option_key_map() as $old => $new ) {
			if ( ! array_key_exists( $old, $opts ) ) {
				continue;
			}
			$has_new = array_key_exists( $new, $opts ) && '' !== trim( (string) $opts[ $new ] );
			if ( ! $has_new ) {
				$opts[ $new ] = $opts[ $old ];
			}
			unset( $opts[ $old ] );
		}
		return $opts;
	}
}

if ( ! function_exists( 'spokora_option_field_types' ) ) {
	/**
	 * Whitelist for spokora_options. Unknown keys are dropped on save.
	 *
	 * @return array<string, string>
	 */
	function spokora_option_field_types() {
		return array(
			'agency_roi_dashboard'        => 'flag',
			'seo_output'                  => 'flag',
			'global_desc'                 => 'textarea',
			'og_image'                    => 'url',
			'home_title'                  => 'text',
			'home_desc'                   => 'textarea',
			'schema_type'                 => 'schema_type',
			'org_name'                    => 'text',
			'logo_url'                    => 'url',
			'public_email'                => 'email',
			'schema_phone'                => 'text',
			'schema_street'               => 'text',
			'schema_city'                 => 'text',
			'schema_region'               => 'text',
			'schema_zip'                  => 'text',
			'latitude'                    => 'text',
			'longitude'                   => 'text',
			'founder_name'                => 'text',
			'founder_title'               => 'text',
			'services'                    => 'textarea',
			'socials'                     => 'textarea',
			'global_schema_json'          => 'schema_json',
			'llms_preamble'               => 'textarea',
			'llms_preamble_locked'        => 'flag',
			'llms_hub_generated_sections' => 'flag',
			'llms_brand_vocab'            => 'textarea',
			'llms_contact_url'            => 'text',
		);
	}
}

if ( ! function_exists( 'spokora_sanitize_option_field' ) ) {
	/**
	 * @param string $type Field type from spokora_option_field_types().
	 * @param mixed  $val  Scalar value.
	 * @return string
	 */
	function spokora_sanitize_option_field( $type, $val ) {
		if ( is_array( $val ) || is_object( $val ) ) {
			return '';
		}
		$raw = wp_unslash( (string) $val );
		switch ( $type ) {
			case 'flag':
				return ( '1' === (string) $raw ) ? '1' : '0';
			case 'url':
				return esc_url_raw( $raw );
			case 'email':
				return sanitize_email( $raw );
			case 'textarea':
				return sanitize_textarea_field( $raw );
			case 'schema_json':
				return wp_strip_all_tags( $raw );
			case 'schema_type':
				return function_exists( 'spokora_sanitize_schema_type' )
					? spokora_sanitize_schema_type( $raw )
					: sanitize_text_field( $raw );
			case 'text':
			default:
				return sanitize_text_field( $raw );
		}
	}
}

if ( ! function_exists( 'spokora_legacy_post_meta_map' ) ) {
	/**
	 * @return array<string, string>
	 */
	function spokora_legacy_post_meta_map() {
		return array(
			'_hw_seo_title'          => '_spokora_seo_title',
			'_hw_seo_desc'           => '_spokora_seo_desc',
			'_hw_seo_score'          => '_spokora_seo_score',
			'_hw_seo_score_updated'  => '_spokora_seo_score_updated',
			'_hw_seo_score_exclude'  => '_spokora_seo_score_exclude',
		);
	}
}

if ( ! function_exists( 'spokora_migrate_legacy_hw_storage' ) ) {
	/**
	 * Copy legacy keys once per site, then stop reading them.
	 */
	function spokora_migrate_legacy_hw_storage() {
		if ( '1' === get_option( 'spokora_legacy_hw_keys_v1', '' ) ) {
			return;
		}

		$opts = get_option( 'spokora_options', array() );
		if ( is_array( $opts ) ) {
			$next = spokora_rename_legacy_option_keys( $opts );
			if ( $next !== $opts ) {
				update_option( 'spokora_options', $next );
			}
		}

		global $wpdb;
		foreach ( spokora_legacy_post_meta_map() as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time copy of legacy SEO meta. Keys are fixed strings.
			$copied = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
					SELECT old.post_id, %s, old.meta_value
					FROM {$wpdb->postmeta} AS old
					LEFT JOIN {$wpdb->postmeta} AS neu
						ON neu.post_id = old.post_id AND neu.meta_key = %s
					WHERE old.meta_key = %s AND neu.meta_id IS NULL",
					$new,
					$new,
					$old
				)
			);
			if ( false === $copied ) {
				return;
			}
		}

		update_option( 'spokora_legacy_hw_keys_v1', '1', false );
	}
}

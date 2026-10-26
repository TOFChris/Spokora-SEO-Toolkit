<?php
/**
 * GA4 helpers for Cookie consent (ID or full Google tag paste).
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

if ( ! function_exists( 'spokora_sanitize_ga_measurement_id' ) ) {
	/**
	 * @param string $id Raw.
	 * @return string Valid G- ID or empty.
	 */
	function spokora_sanitize_ga_measurement_id( $id ) {
		$id = strtoupper( sanitize_text_field( (string) $id ) );
		return preg_match( '/^G-[A-Z0-9]+$/', $id ) ? $id : '';
	}
}

if ( ! function_exists( 'spokora_extract_ga_measurement_id' ) ) {
	/**
	 * Accept a Measurement ID or Google’s full gtag/gtm.js paste.
	 *
	 * @param string $raw ID or HTML/JS snippet.
	 * @return string Valid G- ID or empty.
	 */
	function spokora_extract_ga_measurement_id( $raw ) {
		$raw = is_string( $raw ) ? $raw : '';
		$trim = trim( $raw );
		if ( '' === $trim ) {
			return '';
		}
		$as_id = spokora_sanitize_ga_measurement_id( $trim );
		if ( '' !== $as_id ) {
			return $as_id;
		}
		if ( preg_match( '/\b(G-[A-Z0-9]+)\b/i', $raw, $match ) ) {
			return spokora_sanitize_ga_measurement_id( $match[1] );
		}
		return '';
	}
}

if ( ! function_exists( 'spokora_analytics_measurement_id' ) ) {
	/**
	 * Precedence: wp-config → consent settings → filter.
	 *
	 * @return string
	 */
	function spokora_analytics_measurement_id() {
		if ( defined( 'SPOKORA_GA_MEASUREMENT_ID' ) && is_string( SPOKORA_GA_MEASUREMENT_ID ) && '' !== SPOKORA_GA_MEASUREMENT_ID ) {
			return spokora_sanitize_ga_measurement_id( SPOKORA_GA_MEASUREMENT_ID );
		}
		$consent = get_option( 'spokora_consent_settings', array() );
		if ( is_array( $consent ) && ! empty( $consent['ga_id'] ) ) {
			$from_consent = spokora_sanitize_ga_measurement_id( (string) $consent['ga_id'] );
			if ( '' !== $from_consent ) {
				return $from_consent;
			}
		}
		$filtered = apply_filters( 'spokora_ga_measurement_id', '' );
		return spokora_sanitize_ga_measurement_id( (string) $filtered );
	}
}

if ( ! function_exists( 'spokora_ga_measurement_config_args' ) ) {
	/**
	 * @return array<string, mixed>
	 */
	function spokora_ga_measurement_config_args() {
		$args = array(
			'anonymize_ip' => true,
		);
		if (
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin-only debug flag for Tag Assistant.
			isset( $_GET['spokora_ga_debug'] )
			&& function_exists( 'current_user_can' )
			&& current_user_can( 'manage_options' )
		) {
			$args['debug_mode'] = true;
		}
		return (array) apply_filters( 'spokora_ga_measurement_config_args', $args );
	}
}

if ( ! function_exists( 'spokora_enqueue_ga4' ) ) {
	/**
	 * Enqueue local GA4 loader (optional; only after a Measurement ID is stored).
	 *
	 * @param bool $analytics_granted Whether analytics_storage is granted.
	 */
	function spokora_enqueue_ga4( $analytics_granted ) {
		static $done = false;
		if ( $done || is_admin() ) {
			return;
		}
		$id = spokora_analytics_measurement_id();
		if ( '' === $id || ! defined( 'SPOKORA_PLUGIN_URL' ) ) {
			return;
		}
		$done = true;
		$ver  = defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '0.1.26';
		wp_enqueue_script(
			'spokora-ga4',
			SPOKORA_PLUGIN_URL . 'assets/js/ga4-loader.js',
			array(),
			$ver,
			false
		);
		wp_localize_script(
			'spokora-ga4',
			'spokoraGa4',
			array(
				'id'      => $id,
				'granted' => $analytics_granted ? 1 : 0,
				'config'  => spokora_ga_measurement_config_args(),
			)
		);
	}
}

if ( ! function_exists( 'spokora_print_ga4_head_after_consent' ) ) {
	/**
	 * GA4 for returning visitors who already accepted.
	 */
	function spokora_print_ga4_head_after_consent() {
		if ( ! function_exists( 'spokora_has_analytics_consent' ) || ! spokora_has_analytics_consent() ) {
			return;
		}
		spokora_enqueue_ga4( true );
	}
}

if ( ! function_exists( 'spokora_print_ga4_consent_mode_defaults' ) ) {
	/**
	 * Consent Mode defaults (storage denied) until Accept.
	 */
	function spokora_print_ga4_consent_mode_defaults() {
		if ( function_exists( 'spokora_has_analytics_consent' ) && spokora_has_analytics_consent() ) {
			return;
		}
		spokora_enqueue_ga4( false );
	}
}

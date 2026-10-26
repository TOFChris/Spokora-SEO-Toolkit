<?php
/**
 * Module: Spokora SEO Engine
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Access denied.' );
}

class SPOKORA_SEO {

	public function __construct() {
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20, 1 );
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 2 );
		add_action( 'wp_head', array( $this, 'output_json_ld' ), 3 );
	}

	public function filter_document_title( $title ) {
		if ( is_admin() || ! spokora_should_output_seo() ) {
			return $title;
		}

		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_spokora_seo_title', true );
			if ( is_string( $custom ) && $custom !== '' ) {
				return sanitize_text_field( $custom );
			}
		}

		$spokora_opts = get_option( 'spokora_options' );
		if ( is_array( $spokora_opts ) && ( is_front_page() || is_home() ) && ! empty( $spokora_opts['home_title'] ) ) {
			return sanitize_text_field( $spokora_opts['home_title'] );
		}

		return $title;
	}

	public function output_meta_description() {
		if ( is_admin() || ! spokora_should_output_seo() ) {
			return;
		}

		$desc = '';

		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_spokora_seo_desc', true );
			if ( is_string( $custom ) && $custom !== '' ) {
				$desc = sanitize_textarea_field( $custom );
			}
		}

		if ( $desc === '' && ( is_front_page() || is_home() ) ) {
			$spokora_opts = get_option( 'spokora_options' );
			if ( is_array( $spokora_opts ) ) {
				if ( ! empty( $spokora_opts['home_desc'] ) ) {
					$desc = sanitize_textarea_field( $spokora_opts['home_desc'] );
				} elseif ( ! empty( $spokora_opts['global_desc'] ) ) {
					$desc = sanitize_textarea_field( $spokora_opts['global_desc'] );
				}
			}
		}

		if ( $desc === '' ) {
			return;
		}

		echo "\n" . '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}

	public function output_json_ld() {
		if ( is_admin() || ! spokora_should_output_seo() ) {
			return;
		}

		$page_ld = null;
		if ( is_singular() ) {
			$raw = get_post_meta( get_queried_object_id(), '_spokora_custom_schema', true );
			if ( is_string( $raw ) && $raw !== '' ) {
				$page_ld = $this->normalize_json_ld_string( $raw );
			}
		}

		if ( $page_ld !== null ) {
			$this->print_ld_json_script( $page_ld );
			return;
		}

		$spokora_opts = get_option( 'spokora_options' );
		if ( ! is_array( $spokora_opts ) ) {
			$spokora_opts = array();
		}

		$global_raw = $spokora_opts['global_schema_json'] ?? '';
		if ( is_string( $global_raw ) && $global_raw !== '' ) {
			$global_ld = $this->normalize_json_ld_string( $global_raw );
			if ( $global_ld !== null ) {
				$this->print_ld_json_script( $global_ld );
				return;
			}
		}

		$this->print_ld_json_script( $this->build_dynamic_schema( $spokora_opts ) );
	}

	/**
	 * @param mixed $data Decoded JSON (array) to output.
	 */
	private function print_ld_json_script( $data ) {
		if ( function_exists( 'spokora_echo_ld_json_script' ) ) {
			spokora_echo_ld_json_script( $data );
		}
	}

	/**
	 * @param string $raw User-supplied JSON string.
	 * @return array<string, mixed>|null
	 */
	private function normalize_json_ld_string( $raw ) {
		$t = trim( wp_unslash( $raw ) );
		if ( $t === '' ) {
			return null;
		}
		$decoded = json_decode( $t, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return null;
		}
		return $decoded;
	}

	/**
	 * @param array<string, mixed> $spokora_opts Options array.
	 * @return array<string, mixed>
	 */
	private function build_dynamic_schema( array $spokora_opts ) {
		$org_name     = $spokora_opts['org_name'] ?? get_bloginfo( 'name' );
		$b_type       = $spokora_opts['schema_type'] ?? 'ProfessionalService';
		$logo_url     = $spokora_opts['logo_url'] ?? '';
		$email        = $spokora_opts['public_email'] ?? '';
		$phone        = $spokora_opts['schema_phone'] ?? '';

		$street   = $spokora_opts['schema_street'] ?? '';
		$locality = $spokora_opts['schema_city'] ?? '';
		$region   = $spokora_opts['schema_region'] ?? '';
		$zip      = $spokora_opts['schema_zip'] ?? '';

		$latitude  = $spokora_opts['latitude'] ?? '';
		$longitude = $spokora_opts['longitude'] ?? '';

		$founder      = $spokora_opts['founder_name'] ?? '';
		$founder_role = $spokora_opts['founder_title'] ?? '';

		$services_raw   = $spokora_opts['services'] ?? '';
		$services_array = ! empty( $services_raw ) ? array_map( 'trim', explode( ',', $services_raw ) ) : array();
		$offers         = array();
		foreach ( $services_array as $service ) {
			if ( ! empty( $service ) ) {
				$offers[] = array(
					'@type'       => 'Offer',
					'itemOffered' => array( '@type' => 'Service', 'name' => $service ),
				);
			}
		}

		$socials_raw   = $spokora_opts['socials'] ?? '';
		$socials_array = ! empty( $socials_raw ) ? array_map( 'trim', explode( ',', $socials_raw ) ) : array();

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => $b_type,
					'@id'   => trailingslashit( site_url() ) . '#organization',
					'name'  => $org_name,
					'url'   => trailingslashit( site_url() ),
				),
				array(
					'@type'     => 'WebSite',
					'@id'       => trailingslashit( site_url() ) . '#website',
					'url'       => trailingslashit( site_url() ),
					'name'      => $org_name,
					'publisher' => array( '@id' => trailingslashit( site_url() ) . '#organization' ),
				),
			),
		);

		if ( ! empty( $logo_url ) ) {
			$schema['@graph'][0]['logo']  = array( '@type' => 'ImageObject', 'url' => $logo_url );
			$schema['@graph'][0]['image'] = $logo_url;
		}
		if ( ! empty( $email ) ) {
			$schema['@graph'][0]['email'] = $email;
		}
		if ( ! empty( $phone ) ) {
			$schema['@graph'][0]['telephone'] = $phone;
		}
		if ( ! empty( $socials_array ) ) {
			$schema['@graph'][0]['sameAs'] = $socials_array;
		}

		if ( ! empty( $street ) || ! empty( $locality ) ) {
			$schema['@graph'][0]['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $locality,
				'addressRegion'   => $region,
				'postalCode'      => $zip,
				'addressCountry'  => 'US',
			);
		}

		if ( ! empty( $latitude ) && ! empty( $longitude ) ) {
			$schema['@graph'][0]['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $latitude,
				'longitude' => $longitude,
			);
		}

		if ( ! empty( $founder ) ) {
			$schema['@graph'][0]['founder'] = array(
				'@type'    => 'Person',
				'name'     => $founder,
				'jobTitle' => $founder_role,
			);
		}

		if ( ! empty( $offers ) ) {
			$schema['@graph'][0]['hasOfferCatalog'] = array(
				'@type'           => 'OfferCatalog',
				'name'            => 'Core Services',
				'itemListElement' => $offers,
			);
		}

		return $schema;
	}
}
new SPOKORA_SEO();

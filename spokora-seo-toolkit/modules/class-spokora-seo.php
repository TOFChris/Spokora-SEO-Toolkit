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
		add_action( 'wp_head', array( $this, 'output_canonical' ), 1 );
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 2 );
		add_action( 'wp_head', array( $this, 'output_json_ld' ), 3 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 20 );
		add_action( 'send_headers', array( $this, 'send_robots_header' ) );
	}

	/**
	 * Canonical link for the current public request.
	 *
	 * Singular posts use the editor override when it is a valid URL, otherwise
	 * the WordPress permalink. Archives use the archive URL, including pagination.
	 *
	 * @return string
	 */
	public function canonical_url() {
		if ( is_admin() || is_feed() || is_trackback() || is_preview() || is_embed() || is_404() || is_customize_preview() ) {
			return '';
		}

		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
			$custom  = $post_id ? spokora_sanitize_canonical_url( get_post_meta( $post_id, '_spokora_seo_canonical', true ) ) : '';
			if ( '' !== $custom ) {
				return $custom;
			}
			$core = $post_id ? wp_get_canonical_url( $post_id ) : '';
			return is_string( $core ) ? $core : '';
		}

		$url = '';
		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$url        = $posts_page ? (string) get_permalink( $posts_page ) : home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$link = ( $term instanceof WP_Term ) ? get_term_link( $term ) : '';
			$url  = is_string( $link ) ? $link : '';
		} elseif ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			$link = get_post_type_archive_link( (string) $post_type );
			$url  = is_string( $link ) ? $link : '';
		} elseif ( is_author() ) {
			$url = (string) get_author_posts_url( (int) get_queried_object_id() );
		} elseif ( is_day() ) {
			$url = (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
		} elseif ( is_month() ) {
			$url = (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
		} elseif ( is_year() ) {
			$url = (string) get_year_link( (int) get_query_var( 'year' ) );
		} elseif ( is_search() ) {
			$url = (string) get_search_link();
		}

		if ( '' === $url ) {
			return '';
		}

		$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $paged > 1 ) {
			$paged_url = get_pagenum_link( $paged );
			if ( is_string( $paged_url ) && '' !== $paged_url ) {
				$url = $paged_url;
			}
		}

		return $url;
	}

	/**
	 * Print the canonical tag and remove WordPress core's singular tag so the page has one.
	 */
	public function output_canonical() {
		if ( is_admin() || ! function_exists( 'spokora_should_output_seo' ) || ! spokora_should_output_seo() ) {
			return;
		}

		$url = $this->canonical_url();
		if ( '' === $url ) {
			return;
		}

		remove_action( 'wp_head', 'rel_canonical' );
		echo "\n" . '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
	}

	/**
	 * Apply the editor robots directive to the core robots meta tag.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public function filter_robots( $robots ) {
		if ( ! is_array( $robots ) || is_admin() || ! function_exists( 'spokora_should_output_seo' ) || ! spokora_should_output_seo() || ! is_singular() ) {
			return $robots;
		}

		$directive = spokora_get_post_robots_directive( (int) get_queried_object_id() );
		if ( '' === $directive ) {
			return $robots;
		}

		if ( false !== strpos( $directive, 'noindex' ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		if ( false !== strpos( $directive, 'nofollow' ) ) {
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	/**
	 * Send the same robots directive as an HTTP header when the editor set one.
	 */
	public function send_robots_header() {
		if ( is_admin() || headers_sent() || ! function_exists( 'spokora_should_output_seo' ) || ! spokora_should_output_seo() || ! is_singular() ) {
			return;
		}

		$directive = spokora_get_post_robots_directive( (int) get_queried_object_id() );
		if ( '' === $directive ) {
			return;
		}

		header( 'X-Robots-Tag: ' . $directive, true );
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

<?php
/**
 * JSON-LD starter templates for per-page and global schema fields.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, array{label: string, description: string, json: string}>
 */
function spokora_json_ld_preset_templates() {
	$templates = array(
		'' => array(
			'label'       => __( '— Custom (paste or edit JSON) —', 'spokora-seo-toolkit' ),
			'description' => '',
			'json'        => '',
		),
		'article' => array(
			'label'       => __( 'Article / blog post', 'spokora-seo-toolkit' ),
			'description' => __( 'BlogPosting with headline, URL, and publisher.', 'spokora-seo-toolkit' ),
			'json'        => '{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": "{{PAGE_TITLE}}",
  "url": "{{PAGE_URL}}",
  "mainEntityOfPage": "{{PAGE_URL}}",
  "author": {
    "@type": "Person",
    "name": "{{AUTHOR_NAME}}"
  },
  "publisher": {
    "@type": "Organization",
    "name": "{{SITE_NAME}}",
    "url": "{{SITE_URL}}"
  }
}',
		),
		'faq' => array(
			'label'       => __( 'FAQ page', 'spokora-seo-toolkit' ),
			'description' => __( 'FAQPage with two sample questions — replace copy before publish.', 'spokora-seo-toolkit' ),
			'json'        => '{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What is the first question visitors ask?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Write a concise answer here."
      }
    },
    {
      "@type": "Question",
      "name": "What is the second common question?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Write a concise answer here."
      }
    }
  ]
}',
		),
		'service' => array(
			'label'       => __( 'Service page', 'spokora-seo-toolkit' ),
			'description' => __( 'Service offered by your organization on this URL.', 'spokora-seo-toolkit' ),
			'json'        => '{
  "@context": "https://schema.org",
  "@type": "Service",
  "name": "{{PAGE_TITLE}}",
  "url": "{{PAGE_URL}}",
  "provider": {
    "@type": "Organization",
    "name": "{{SITE_NAME}}",
    "url": "{{SITE_URL}}"
  },
  "areaServed": {
    "@type": "Place",
    "name": "Your service area"
  }
}',
		),
		'local_landing' => array(
			'label'       => __( 'Local landing page', 'spokora-seo-toolkit' ),
			'description' => __( 'WebPage + LocalBusiness reference for geo pages.', 'spokora-seo-toolkit' ),
			'json'        => '{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebPage",
      "@id": "{{PAGE_URL}}#webpage",
      "url": "{{PAGE_URL}}",
      "name": "{{PAGE_TITLE}}",
      "isPartOf": { "@id": "{{SITE_URL}}#website" }
    },
    {
      "@type": "LocalBusiness",
      "@id": "{{SITE_URL}}#localbusiness",
      "name": "{{SITE_NAME}}",
      "url": "{{SITE_URL}}"
    }
  ]
}',
		),
		'howto' => array(
			'label'       => __( 'How-to', 'spokora-seo-toolkit' ),
			'description' => __( 'HowTo with one step — add more steps as needed.', 'spokora-seo-toolkit' ),
			'json'        => '{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "{{PAGE_TITLE}}",
  "description": "Short summary of what this guide teaches.",
  "step": [
    {
      "@type": "HowToStep",
      "name": "Step 1",
      "text": "Describe the first step."
    }
  ]
}',
		),
		'breadcrumb' => array(
			'label'       => __( 'Breadcrumb list', 'spokora-seo-toolkit' ),
			'description' => __( 'BreadcrumbList (pair with visible breadcrumbs on the page).', 'spokora-seo-toolkit' ),
			'json'        => '{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "{{SITE_URL}}"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "{{PAGE_TITLE}}",
      "item": "{{PAGE_URL}}"
    }
  ]
}',
		),
	);

	return apply_filters( 'spokora_json_ld_preset_templates', $templates );
}

/**
 * Replace {{TOKENS}} in a JSON-LD template.
 *
 * @param string $json    Template JSON.
 * @param int    $post_id Post ID (0 for site-level).
 * @return string
 */
function spokora_json_ld_fill_preset_placeholders( $json, $post_id = 0 ) {
	$post_id = (int) $post_id;
	$post    = $post_id > 0 ? get_post( $post_id ) : null;

	$site_name = get_bloginfo( 'name' );
	$site_url  = trailingslashit( home_url( '/' ) );
	$title     = $post ? get_the_title( $post ) : $site_name;
	$page_url  = $post ? get_permalink( $post ) : $site_url;
	if ( ! is_string( $page_url ) || $page_url === '' ) {
		$page_url = $site_url;
	}
	$author = $post ? get_the_author_meta( 'display_name', (int) $post->post_author ) : '';

	$map = array(
		'{{SITE_NAME}}'   => $site_name,
		'{{SITE_URL}}'    => $site_url,
		'{{PAGE_TITLE}}'  => $title,
		'{{PAGE_URL}}'    => $page_url,
		'{{AUTHOR_NAME}}' => $author !== '' ? $author : $site_name,
	);

	return str_replace( array_keys( $map ), array_values( $map ), (string) $json );
}

/**
 * @param string $textarea_id Target textarea element id.
 * @param int    $post_id     Post ID for placeholders.
 * @param string $select_id   Select element id.
 */
function spokora_render_json_ld_preset_field( $textarea_id, $post_id = 0, $select_id = 'spokora_schema_json_preset' ) {
	$presets = spokora_json_ld_preset_templates();
	$filled  = array();
	foreach ( $presets as $id => $preset ) {
		$filled[ $id ] = array(
			'label'       => $preset['label'],
			'description' => $preset['description'],
			'json'        => spokora_json_ld_fill_preset_placeholders( $preset['json'], $post_id ),
		);
	}

	static $instances = array();
	static $script_enqueued = false;

	$instances[] = array(
		'selectId'    => $select_id,
		'textareaId'  => $textarea_id,
		'presets'     => $filled,
		'confirmText' => __( 'Replace the current JSON in the editor with this starter?', 'spokora-seo-toolkit' ),
	);

	if ( ! $script_enqueued ) {
		wp_enqueue_script(
			'spokora-schema-json-presets',
			SPOKORA_PLUGIN_URL . 'assets/js/schema-json-presets.js',
			array( 'jquery' ),
			defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '1.0.0',
			true
		);
		$script_enqueued = true;
		add_action(
			'admin_print_footer_scripts',
			static function () use ( &$instances ) {
				if ( empty( $instances ) ) {
					return;
				}
				wp_localize_script(
					'spokora-schema-json-presets',
					'spokoraSchemaJsonPresets',
					array( 'instances' => $instances )
				);
			},
			5
		);
	}

	echo '<p style="margin: 0 0 8px;">';
	echo '<label for="' . esc_attr( $select_id ) . '" style="font-weight:600;display:block;margin-bottom:4px;">' . esc_html__( 'JSON-LD starter', 'spokora-seo-toolkit' ) . '</label>';
	echo '<select id="' . esc_attr( $select_id ) . '" class="spokora-schema-json-preset" style="width:100%;max-width:100%;">';
	foreach ( $presets as $id => $preset ) {
		echo '<option value="' . esc_attr( (string) $id ) . '">' . esc_html( $preset['label'] ) . '</option>';
	}
	echo '</select>';
	echo '<span class="description spokora-schema-json-preset-desc" style="display:block;margin-top:6px;"></span>';
	echo '</p>';
}

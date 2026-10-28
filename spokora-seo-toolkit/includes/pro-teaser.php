<?php
/**
 * Spokora Pro teasers in Free. SEO stays ungated.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store URL for the Pro pitch (homepage tiers).
 */
function spokora_pro_url() {
	return apply_filters( 'spokora_pro_url', 'https://spokora.com/#tiers' );
}

/**
 * Admin screen that lists Pro without installing it.
 */
function spokora_go_pro_page_slug() {
	return 'spokora-go-pro';
}

/**
 * @return array<int, array{title: string, body: string, items: list<string>}>
 */
function spokora_pro_teaser_groups() {
	return array(
		array(
			'title' => __( 'Your keys, your models', 'spokora-seo-toolkit' ),
			'body'  => __( 'This plugin scores the page. Pro writes drafts from the endpoint you paste — OpenRouter, Gemini, or whatever you already pay for. Keys can lock in wp-config.php.', 'spokora-seo-toolkit' ),
			'items' => array(
				__( 'API Vault', 'spokora-seo-toolkit' ),
				__( 'Title, meta, and AI-doc drafts', 'spokora-seo-toolkit' ),
				__( 'Vision alt text in Media Lab', 'spokora-seo-toolkit' ),
			),
		),
		array(
			'title' => __( 'The rest of a client week', 'spokora-seo-toolkit' ),
			'body'  => __( 'Search Console, ranks, media, social, and a lead inbox live next to the SEO you already have. Same plugin, not a second install.', 'spokora-seo-toolkit' ),
			'items' => array(
				__( 'Search Performance (your GSC)', 'spokora-seo-toolkit' ),
				__( 'Rank tracker, X-Ray, backlinks', 'spokora-seo-toolkit' ),
				__( 'Social Studio and lead router', 'spokora-seo-toolkit' ),
			),
		),
		array(
			'title' => __( 'Agency ops', 'spokora-seo-toolkit' ),
			'body'  => __( 'Programmatic pages, MCP extras, and the telemetry screens we kept out of this plugin so this install stays a clean SEO plugin.', 'spokora-seo-toolkit' ),
			'items' => array(
				__( 'Deployment Matrix', 'spokora-seo-toolkit' ),
				__( 'Site Pulse, visits, tracking', 'spokora-seo-toolkit' ),
				__( 'MCP / Agent Ready extras', 'spokora-seo-toolkit' ),
			),
		),
	);
}

/**
 * Locked rows on Modules. Not a second plugin.
 *
 * @return array<string, array{label: string, summary: string}>
 */
function spokora_pro_locked_modules() {
	return array(
		'api_vault'           => array(
			'label'   => __( 'API Vault', 'spokora-seo-toolkit' ),
			'summary' => __( 'Paste keys once. Optional wp-config.php lock. This plugin never asks for this.', 'spokora-seo-toolkit' ),
		),
		'ai'                  => array(
			'label'   => __( 'AI content', 'spokora-seo-toolkit' ),
			'summary' => __( 'Model drafts for titles, descriptions, and /ai-docs markdown.', 'spokora-seo-toolkit' ),
		),
		'search_performance'  => array(
			'label'   => __( 'Search Performance', 'spokora-seo-toolkit' ),
			'summary' => __( 'Search performance from Google Search Console: queries and pages, how the site shows up in Google. Uses your OAuth.', 'spokora-seo-toolkit' ),
		),
		'rank_tracker'        => array(
			'label'   => __( 'Rank tracker', 'spokora-seo-toolkit' ),
			'summary' => __( 'Keyword positions on their DataForSEO bill, not ours.', 'spokora-seo-toolkit' ),
		),
		'media_lab'           => array(
			'label'   => __( 'Media Lab', 'spokora-seo-toolkit' ),
			'summary' => __( 'Vision alt text and WebP as a week deliverable.', 'spokora-seo-toolkit' ),
		),
		'social'              => array(
			'label'   => __( 'Social Studio', 'spokora-seo-toolkit' ),
			'summary' => __( 'Webhook posts you choose. Not silent auto-spam.', 'spokora-seo-toolkit' ),
		),
		'lead_router'         => array(
			'label'   => __( 'Lead inbox', 'spokora-seo-toolkit' ),
			'summary' => __( 'Form leads into the week, not a second CRM login.', 'spokora-seo-toolkit' ),
		),
		'live_dashboard'      => array(
			'label'   => __( 'Live Dashboard', 'spokora-seo-toolkit' ),
			'summary' => __( 'Telemetry when keys exist. Home in this plugin stays an SEO map on purpose.', 'spokora-seo-toolkit' ),
		),
	);
}

/**
 * Primary CTA markup.
 *
 * @param bool $include_internal Link to the in-plugin Go Pro page.
 */
function spokora_pro_cta_html( $include_internal = false ) {
	unset( $include_internal );
	return '<a class="button button-primary" href="' . esc_url( spokora_pro_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'See Spokora Pro', 'spokora-seo-toolkit' ) . '</a>';
}

/**
 * @param string $variant card|compact|keys
 * @param string $context Optional hook id for copy.
 */
function spokora_render_pro_teaser( $variant = 'compact', $context = '' ) {
	unset( $context );
	$variant = in_array( $variant, array( 'card', 'compact', 'keys' ), true ) ? $variant : 'compact';

	if ( 'keys' === $variant ) {
		echo '<div class="spokora-pro-teaser spokora-pro-teaser--keys">';
		echo '<p><span class="spokora-pro-badge">' . esc_html__( 'Pro', 'spokora-seo-toolkit' ) . '</span> ';
		echo esc_html__( 'Scores and /llms.txt run here. Model drafts need Spokora Pro and a key you already pay for.', 'spokora-seo-toolkit' );
		echo '</p><p class="spokora-pro-teaser__actions">' . wp_kses_post( spokora_pro_cta_html() ) . '</p>';
		echo '</div>';
		return;
	}

	if ( 'compact' === $variant ) {
		echo '<div class="spokora-pro-teaser spokora-pro-teaser--compact">';
		echo '<p><span class="spokora-pro-badge">' . esc_html__( 'Pro', 'spokora-seo-toolkit' ) . '</span> ';
		echo esc_html__( 'This plugin is the SEO toolkit. Pro is the rest of a client week: keys, Search Console, media, leads.', 'spokora-seo-toolkit' );
		echo '</p><p class="spokora-pro-teaser__actions">' . wp_kses_post( spokora_pro_cta_html() ) . '</p>';
		echo '</div>';
		return;
	}

	echo '<aside class="spokora-pro-teaser spokora-pro-teaser--card" aria-labelledby="spokora-pro-teaser-title">';
	echo '<p class="spokora-pro-teaser__kicker"><span class="spokora-pro-badge">' . esc_html__( 'Pro', 'spokora-seo-toolkit' ) . '</span></p>';
	echo '<h2 id="spokora-pro-teaser-title">' . esc_html__( 'Need the rest of the week?', 'spokora-seo-toolkit' ) . '</h2>';
	echo '<p>' . esc_html__( 'Titles, schema, sitemap, and redirects stay free. Upgrade when you want your own models and the agency screens we kept out of this install.', 'spokora-seo-toolkit' ) . '</p>';
	echo '<ul class="spokora-pro-teaser__list">';
	foreach ( array(
		__( 'BYOK vault — we do not resell tokens', 'spokora-seo-toolkit' ),
		__( 'Search Console and rank tools', 'spokora-seo-toolkit' ),
		__( 'Media Lab, social, lead inbox', 'spokora-seo-toolkit' ),
	) as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	echo '</ul>';
	echo '<p class="spokora-pro-teaser__actions">' . wp_kses_post( spokora_pro_cta_html() ) . '</p>';
	echo '</aside>';
}

/**
 * Compact hint used by copied Pro screens (settings, LLMS, editor).
 */
function spokora_maybe_render_gemini_vault_hint() {
	spokora_render_pro_teaser( 'keys' );
}

/**
 * Plugins-list Pro link. No left-menu tab.
 */
class Spokora_Go_Pro {

	public function __construct() {
		if ( defined( 'SPOKORA_PLUGIN_FILE' ) ) {
			add_filter( 'plugin_action_links_' . plugin_basename( SPOKORA_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		}
	}

	/**
	 * @param array<int, string> $links Links.
	 * @return array<int, string>
	 */
	public function plugin_action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$links[] = '<a href="' . esc_url( spokora_pro_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Spokora Pro', 'spokora-seo-toolkit' ) . '</a>';
		return $links;
	}
}

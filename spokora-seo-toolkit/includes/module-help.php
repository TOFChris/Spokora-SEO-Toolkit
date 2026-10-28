<?php
/**
 * One-line module help for Spokora SEO Toolkit.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, array{summary: string, menu?: string}>
 */
function spokora_get_module_help_blurbs() {
	return array(
		'seo'         => array(
			'summary' => __( 'Front-end title, meta description, canonical URL, robots meta, and JSON-LD.', 'spokora-seo-toolkit' ),
		),
		'seo_score'   => array(
			'summary' => __( 'Search SEO + AI readiness scores in the editor and a site overview. Scores do not call a model.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → SEO scores', 'spokora-seo-toolkit' ),
		),
		'metabox'     => array(
			'summary' => __( 'SEO box on posts and pages: keyphrases, title, description, canonical URL, robots, schema.', 'spokora-seo-toolkit' ),
		),
		'llms'        => array(
			'summary' => __( '/llms.txt hub and /ai-docs/{slug}.md for pages you mark as AI docs.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → Configure → AI index', 'spokora-seo-toolkit' ),
		),
		'markdown'    => array(
			'summary' => __( 'Markdown copy of each published post and page at /md/{slug}.md.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → Configure → Markdown copies', 'spokora-seo-toolkit' ),
		),
		'settings'    => array(
			'summary' => __( 'Site-wide SEO defaults and the front-end SEO toggle. Import SEO copies titles, descriptions, and exact 301s from Yoast, Rank Math, AIOSEO, or SEOPress.', 'spokora-seo-toolkit' ),
		),
		'sitemap'     => array(
			'summary' => __( 'XML sitemap, plus news and video sitemaps when those content types apply.', 'spokora-seo-toolkit' ),
		),
		'redirects'   => array(
			'summary' => __( 'Exact 301 rules, including those copied from the old SEO plugin.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → Redirects', 'spokora-seo-toolkit' ),
		),
		'performance' => array(
			'summary' => __( 'Light front-end cleanup (emojis, dashicons, heartbeat). Leaves theme.json / block-theme global styles in place. Not a cache plugin.', 'spokora-seo-toolkit' ),
		),
		'indexnow'    => array(
			'summary' => __( 'Pings IndexNow when a public URL changes. Google does not use IndexNow.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → Search ping', 'spokora-seo-toolkit' ),
		),
		'backups'     => array(
			'summary' => __( 'One-button zip of the database and wp-content. Use before importing an old SEO plugin.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → Backups', 'spokora-seo-toolkit' ),
		),
		'consent'     => array(
			'summary' => __( 'UK/GDPR-style banner: Reject and Accept are equal. Paste a GA4 ID or the whole Google tag; analytics load only after Accept.', 'spokora-seo-toolkit' ),
			'menu'    => __( 'Spokora → Cookies & privacy', 'spokora-seo-toolkit' ),
		),
	);
}

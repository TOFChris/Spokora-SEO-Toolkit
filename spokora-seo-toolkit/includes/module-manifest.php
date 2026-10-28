<?php
/**
 * Free module list only.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, array{file: string, class: string, bootstrap: string, label: string}>
 */
function spokora_get_module_manifest() {
	return array(
		'seo'         => array(
			'file'      => 'class-spokora-seo.php',
			'class'     => 'SPOKORA_SEO',
			'bootstrap' => 'file',
			'label'     => 'SEO & schema',
		),
		'performance' => array(
			'file'      => 'class-spokora-performance.php',
			'class'     => 'Spokora_Performance_Engine',
			'bootstrap' => 'file',
			'label'     => 'Performance',
		),
		'settings'    => array(
			'file'      => 'class-spokora-settings.php',
			'class'     => 'Spokora_Settings_Engine',
			'bootstrap' => 'file',
			'label'     => 'Settings UI & migration',
		),
		'metabox'     => array(
			'file'      => 'class-spokora-metabox.php',
			'class'     => 'Spokora_MetaBox_Engine',
			'bootstrap' => 'file',
			'label'     => 'Page SEO meta box',
		),
		'sitemap'     => array(
			'file'      => 'class-spokora-sitemap.php',
			'class'     => 'Spokora_Sitemap_Engine',
			'bootstrap' => 'file',
			'label'     => 'XML sitemap',
		),
		'redirects'   => array(
			'file'      => 'class-spokora-redirects.php',
			'class'     => 'Spokora_Redirect_Engine',
			'bootstrap' => 'file',
			'label'     => 'Redirects',
		),
		'llms'        => array(
			'file'      => 'class-spokora-llms.php',
			'class'     => 'Spokora_Llms_Engine',
			'bootstrap' => 'file',
			'label'     => 'AI index (llms.txt)',
		),
		'markdown'    => array(
			'file'      => 'class-spokora-markdown.php',
			'class'     => 'Spokora_Markdown',
			'bootstrap' => 'file',
			'label'     => 'Markdown',
		),
		'seo_score'   => array(
			'file'      => 'class-spokora-seo-score.php',
			'class'     => 'Spokora_SEO_Score_Engine',
			'bootstrap' => 'file',
			'label'     => 'SEO & AI readiness scores',
		),
		'indexnow'    => array(
			'file'      => 'class-spokora-indexnow.php',
			'class'     => 'Spokora_IndexNow',
			'bootstrap' => 'file',
			'label'     => 'Search ping (IndexNow)',
		),
		'backups'     => array(
			'file'      => 'class-spokora-backups.php',
			'class'     => 'Spokora_Backups',
			'bootstrap' => 'file',
			'label'     => 'Backups',
		),
		'consent'     => array(
			'file'      => 'class-spokora-consent.php',
			'class'     => 'Spokora_Consent',
			'bootstrap' => 'file',
			'label'     => 'Cookies & privacy',
		),
	);
}

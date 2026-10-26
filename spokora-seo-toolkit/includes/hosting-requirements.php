<?php
/**
 * WordPress websites scope (plugin copy — independent of store theme).
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Public requirements page on spokora.com.
 */
function spokora_requirements_url() {
	return 'https://spokora.com/requirements/';
}

/**
 * Short admin / setup notice.
 */
function spokora_self_hosted_admin_notice_text() {
	return __(
		'Spokora SEO Toolkit is for WordPress websites.',
		'spokora-seo-toolkit'
	);
}

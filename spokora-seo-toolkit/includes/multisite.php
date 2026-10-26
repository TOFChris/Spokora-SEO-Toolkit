<?php
/**
 * WordPress multisite: network activation, new blogs, ThreeWP Broadcast meta.
 *
 * Options, redirects, Business Info, and sitemaps stay per site.
 * Hidden post meta (_spokora_*) is copied when Broadcast pushes a post.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return bool
 */
function spokora_free_is_network_active() {
	if ( ! is_multisite() ) {
		return false;
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active_for_network( plugin_basename( SPOKORA_PLUGIN_FILE ) );
}

/**
 * @return array<int, WP_Site>
 */
function spokora_free_public_sites() {
	if ( ! is_multisite() ) {
		return array();
	}
	$sites = get_sites(
		array(
			'number'   => 500,
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
		)
	);
	return is_array( $sites ) ? $sites : array();
}

/**
 * @param int      $blog_id Blog ID.
 * @param callable $cb      Callback.
 * @return mixed
 */
function spokora_free_run_on_blog( $blog_id, $cb ) {
	$blog_id = (int) $blog_id;
	if ( $blog_id <= 0 || ! is_callable( $cb ) ) {
		return null;
	}
	switch_to_blog( $blog_id );
	try {
		return $cb();
	} finally {
		restore_current_blog();
	}
}

/**
 * Network Activate: seed every existing blog.
 *
 * @param int $current_blog_id Blog that may show Quick setup this request.
 */
function spokora_free_bootstrap_network( $current_blog_id ) {
	$current_blog_id = (int) $current_blog_id;
	foreach ( spokora_free_public_sites() as $site ) {
		$bid = (int) $site->blog_id;
		spokora_free_run_on_blog(
			$bid,
			static function () use ( $bid, $current_blog_id ) {
				if ( function_exists( 'spokora_activate_current_blog' ) ) {
					spokora_activate_current_blog( $bid === $current_blog_id );
				}
			}
		);
	}
}

/**
 * @param WP_Site $new_site New site.
 */
function spokora_free_on_initialize_site( $new_site ) {
	if ( ! spokora_free_is_network_active() || ! $new_site instanceof WP_Site ) {
		return;
	}
	spokora_free_run_on_blog(
		(int) $new_site->blog_id,
		static function () {
			if ( function_exists( 'spokora_activate_current_blog' ) ) {
				spokora_activate_current_blog( false );
			}
		}
	);
}
add_action( 'wp_initialize_site', 'spokora_free_on_initialize_site', 20, 1 );

/**
 * @return list<string>
 */
function spokora_free_broadcast_meta_prefixes() {
	return apply_filters(
		'spokora_free_broadcast_meta_prefixes',
		array(
			'_spokora_',
		)
	);
}

/**
 * @param string $key Meta key.
 * @return bool
 */
function spokora_free_broadcast_meta_key_is_ours( $key ) {
	$key = (string) $key;
	foreach ( spokora_free_broadcast_meta_prefixes() as $prefix ) {
		$prefix = (string) $prefix;
		if ( '' !== $prefix && 0 === strpos( $key, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param object $action Broadcast started action.
 */
function spokora_free_threewp_include_hidden_meta( $action ) {
	if ( ! is_object( $action ) || empty( $action->broadcasting_data ) ) {
		return;
	}
	$bcd = $action->broadcasting_data;
	if ( ! is_object( $bcd ) || ! method_exists( $bcd, 'custom_fields' ) ) {
		return;
	}
	$post_id = 0;
	if ( isset( $bcd->post ) && is_object( $bcd->post ) && ! empty( $bcd->post->ID ) ) {
		$post_id = (int) $bcd->post->ID;
	}
	if ( $post_id <= 0 ) {
		return;
	}
	$cf = $bcd->custom_fields();
	if ( ! is_object( $cf ) || ! method_exists( $cf, 'add' ) ) {
		return;
	}
	foreach ( array_keys( get_post_meta( $post_id ) ) as $key ) {
		if ( spokora_free_broadcast_meta_key_is_ours( (string) $key ) ) {
			$cf->add( $key );
		}
	}
}
add_action( 'threewp_broadcast_broadcasting_started', 'spokora_free_threewp_include_hidden_meta', 20 );

/**
 * @param object $bcd Broadcasting data.
 * @return int
 */
function spokora_free_threewp_child_post_id( $bcd ) {
	if ( ! is_object( $bcd ) ) {
		return 0;
	}
	if ( isset( $bcd->new_post ) ) {
		$new = $bcd->new_post;
		if ( is_object( $new ) && ! empty( $new->ID ) ) {
			return (int) $new->ID;
		}
		if ( is_array( $new ) && ! empty( $new['ID'] ) ) {
			return (int) $new['ID'];
		}
	}
	return 0;
}

/**
 * @param object $action Broadcast action on the child blog.
 */
function spokora_free_threewp_copy_hidden_meta( $action ) {
	if ( ! is_object( $action ) || empty( $action->broadcasting_data ) ) {
		return;
	}
	$bcd      = $action->broadcasting_data;
	$child_id = spokora_free_threewp_child_post_id( $bcd );
	$parent_id = 0;
	if ( isset( $bcd->post ) && is_object( $bcd->post ) && ! empty( $bcd->post->ID ) ) {
		$parent_id = (int) $bcd->post->ID;
	}
	$parent_blog = isset( $bcd->parent_blog_id ) ? (int) $bcd->parent_blog_id : 0;
	if ( $child_id <= 0 || $parent_id <= 0 || $parent_blog <= 0 ) {
		return;
	}
	$meta = spokora_free_run_on_blog(
		$parent_blog,
		static function () use ( $parent_id ) {
			return get_post_meta( $parent_id );
		}
	);
	if ( ! is_array( $meta ) ) {
		return;
	}
	foreach ( $meta as $key => $values ) {
		if ( ! spokora_free_broadcast_meta_key_is_ours( (string) $key ) ) {
			continue;
		}
		delete_post_meta( $child_id, $key );
		foreach ( (array) $values as $raw ) {
			add_post_meta( $child_id, $key, maybe_unserialize( $raw ) );
		}
	}
}
add_action( 'threewp_broadcast_broadcasting_before_restore_current_blog', 'spokora_free_threewp_copy_hidden_meta', 20 );

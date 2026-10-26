<?php
/**
 * Width and height on content images that do not have them.
 *
 * WordPress already does this for images it prints with an attachment id.
 * This covers the remaining img tags in post content, using the local file.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $image         Image HTML.
 * @param string $context       Filter context.
 * @param int    $attachment_id Attachment id when WordPress knows it.
 * @return string
 */
function spokora_content_image_dimensions( $image, $context, $attachment_id ) {
	unset( $context );
	if ( ! is_string( $image ) || false === stripos( $image, '<img' ) ) {
		return $image;
	}
	$has_width  = (bool) preg_match( '/\swidth\s*=\s*["\']?\d+/i', $image );
	$has_height = (bool) preg_match( '/\sheight\s*=\s*["\']?\d+/i', $image );
	if ( $has_width && $has_height ) {
		return $image;
	}
	if ( ! preg_match( '/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $image, $match ) ) {
		return $image;
	}
	$src = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
	if ( '' === $src || str_starts_with( $src, 'data:' ) ) {
		return $image;
	}
	if ( str_contains( strtolower( strtok( $src, '?' ) ), '.svg' ) ) {
		return $image;
	}
	$size = spokora_content_image_size( $src, (int) $attachment_id );
	if ( $size['width'] < 1 || $size['height'] < 1 ) {
		return $image;
	}
	$width  = $size['width'];
	$height = $size['height'];
	if ( $has_width && preg_match( '/\swidth\s*=\s*["\']?(\d+)/i', $image, $given ) ) {
		$known  = (int) $given[1];
		$height = (int) round( $known * ( $size['height'] / $size['width'] ) );
		$width  = $known;
	} elseif ( $has_height && preg_match( '/\sheight\s*=\s*["\']?(\d+)/i', $image, $given ) ) {
		$known = (int) $given[1];
		$width = (int) round( $known * ( $size['width'] / $size['height'] ) );
		$height = $known;
	}
	if ( $width < 1 || $height < 1 ) {
		return $image;
	}
	if ( ! $has_width ) {
		$image = preg_replace( '/<img\b/i', '<img width="' . $width . '"', $image, 1 );
	}
	if ( ! $has_height ) {
		$image = preg_replace( '/<img\b/i', '<img height="' . $height . '"', $image, 1 );
	}
	return is_string( $image ) ? $image : '';
}
add_filter( 'wp_content_img_tag', 'spokora_content_image_dimensions', 20, 3 );

/**
 * Pixel size for a content image URL.
 *
 * @param string $src           Image URL or path.
 * @param int    $attachment_id Attachment id.
 * @return array{width: int, height: int}
 */
function spokora_content_image_size( $src, $attachment_id ) {
	$empty = array(
		'width'  => 0,
		'height' => 0,
	);
	$id    = $attachment_id > 0 ? $attachment_id : spokora_content_image_attachment_id( $src );
	$base  = strtolower( (string) wp_basename( (string) strtok( $src, '?' ) ) );
	if ( $id > 0 ) {
		$meta = wp_get_attachment_metadata( $id );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['file'] ) && strtolower( wp_basename( (string) $meta['file'] ) ) === $base && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				return array(
					'width'  => (int) $meta['width'],
					'height' => (int) $meta['height'],
				);
			}
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size ) {
					if ( ! is_array( $size ) || empty( $size['file'] ) || empty( $size['width'] ) || empty( $size['height'] ) ) {
						continue;
					}
					if ( strtolower( (string) $size['file'] ) === $base ) {
						return array(
							'width'  => (int) $size['width'],
							'height' => (int) $size['height'],
						);
					}
				}
			}
		}
	}
	$path = spokora_content_image_local_path( $src );
	if ( '' === $path || ! function_exists( 'wp_getimagesize' ) ) {
		return $empty;
	}
	$info = wp_getimagesize( $path );
	if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
		return $empty;
	}
	return array(
		'width'  => (int) $info[0],
		'height' => (int) $info[1],
	);
}

/**
 * @param string $src Image URL.
 */
function spokora_content_image_attachment_id( $src ) {
	static $cache = array();
	$src = spokora_content_image_absolute_url( $src );
	if ( '' === $src ) {
		return 0;
	}
	if ( isset( $cache[ $src ] ) ) {
		return $cache[ $src ];
	}
	$id            = function_exists( 'attachment_url_to_postid' ) ? (int) attachment_url_to_postid( $src ) : 0;
	$cache[ $src ] = $id;
	return $id;
}

/**
 * @param string $src Image URL.
 */
function spokora_content_image_absolute_url( $src ) {
	$src = trim( (string) $src );
	if ( '' === $src || str_starts_with( $src, 'data:' ) ) {
		return '';
	}
	if ( str_starts_with( $src, '//' ) ) {
		$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
	} elseif ( str_starts_with( $src, '/' ) ) {
		$src = home_url( $src );
	}
	return esc_url_raw( $src );
}

/**
 * Local uploads path for a same-site image. Empty for remote files.
 *
 * @param string $src Image URL.
 */
function spokora_content_image_local_path( $src ) {
	$url = spokora_content_image_absolute_url( $src );
	if ( '' === $url ) {
		return '';
	}
	$path    = (string) wp_parse_url( strtok( $url, '?' ), PHP_URL_PATH );
	$uploads = wp_get_upload_dir();
	$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
	$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
	if ( '' === $baseurl || '' === $basedir || '' === $path ) {
		return '';
	}
	$base_path = (string) wp_parse_url( $baseurl, PHP_URL_PATH );
	if ( '' === $base_path || ! str_starts_with( $path, $base_path ) ) {
		return '';
	}
	$rel  = substr( $path, strlen( $base_path ) );
	$file = wp_normalize_path( $basedir . $rel );
	$root = wp_normalize_path( $basedir );
	if ( ! str_starts_with( $file, $root ) || ! is_file( $file ) ) {
		return '';
	}
	return $file;
}

<?php
/**
 * News and video markup for any public post.
 *
 * A news article from the last two days is listed at /news-sitemap.xml.
 * A page with an https video link is listed at /video-sitemap.xml.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * https video URL saved on the post, or on a video episode.
 *
 * @param int $post_id Post id.
 */
function spokora_content_video_url( $post_id ) {
	$url = (string) get_post_meta( $post_id, '_spokora_video_url', true );
	if ( '' === $url ) {
		$kind = (string) get_post_meta( $post_id, '_spokora_episode_kind', true );
		if ( 'video' === $kind ) {
			$url = (string) get_post_meta( $post_id, '_spokora_episode_media', true );
		}
	}
	if ( '' === $url || 0 !== strpos( $url, 'https://' ) ) {
		return '';
	}
	return $url;
}

/**
 * @param int $post_id Post id.
 */
function spokora_content_is_news( $post_id ) {
	return 'news' === (string) get_post_meta( $post_id, '_spokora_content_kind', true );
}

/**
 * ISO-8601 duration, or an empty string.
 *
 * @param int $post_id Post id.
 */
function spokora_content_video_duration( $post_id ) {
	$seconds = (int) get_post_meta( $post_id, '_spokora_video_seconds', true );
	if ( $seconds < 1 ) {
		$raw = (string) get_post_meta( $post_id, '_spokora_episode_duration', true );
		if ( preg_match( '/^(\d+):(\d{2})(?::(\d{2}))?$/', $raw, $m ) ) {
			$seconds = isset( $m[3] ) ? ( (int) $m[1] * 3600 ) + ( (int) $m[2] * 60 ) + (int) $m[3] : ( (int) $m[1] * 60 ) + (int) $m[2];
		}
	}
	return $seconds > 0 ? 'PT' . $seconds . 'S' : '';
}

/**
 * Print article, news, or video schema on a single public item.
 */
function spokora_content_schema() {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return;
	}
	if ( '' !== trim( (string) get_post_meta( $post->ID, '_spokora_custom_schema', true ) ) ) {
		return;
	}
	$kind = (string) get_post_meta( $post->ID, '_spokora_content_kind', true );
	$video = spokora_content_video_url( $post->ID );
	if ( '' === $kind && '' === $video && 'post' !== $post->post_type ) {
		return;
	}
	$image = get_the_post_thumbnail_url( $post, 'full' );
	$desc  = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '' );
	$graph = array();
	if ( 'news' === $kind || 'article' === $kind || ( '' === $kind && 'post' === $post->post_type && '' === $video ) ) {
		$article = array(
			'@type'         => 'news' === $kind ? 'NewsArticle' : 'Article',
			'headline'      => get_the_title( $post ),
			'datePublished' => get_post_time( 'c', true, $post ),
			'dateModified'  => get_post_modified_time( 'c', true, $post ),
			'mainEntityOfPage' => get_permalink( $post ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
		);
		if ( is_string( $image ) && '' !== $image ) {
			$article['image'] = array( $image );
		}
		if ( '' !== $desc ) {
			$article['description'] = $desc;
		}
		$graph[] = $article;
	}
	if ( '' !== $video || 'video' === $kind ) {
		$node = array(
			'@type'        => 'VideoObject',
			'name'         => get_the_title( $post ),
			'description'  => '' !== $desc ? $desc : get_the_title( $post ),
			'uploadDate'   => get_post_time( 'c', true, $post ),
			'contentUrl'   => $video,
		);
		if ( is_string( $image ) && '' !== $image ) {
			$node['thumbnailUrl'] = $image;
		}
		$duration = spokora_content_video_duration( $post->ID );
		if ( '' !== $duration ) {
			$node['duration'] = $duration;
		}
		if ( '' === $video ) {
			unset( $node['contentUrl'] );
		}
		$graph[] = $node;
	}
	if ( ! $graph ) {
		return;
	}
	spokora_echo_ld_json_script(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		)
	);
}
add_action( 'wp_head', 'spokora_content_schema', 20 );

/**
 * News and video sitemaps. Runs before the general sitemap, which also matches these paths.
 */
function spokora_content_sitemaps() {
	$path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
	if ( preg_match( '#/news-sitemap\.xml$#', $path ) ) {
		spokora_content_news_sitemap();
	}
	if ( preg_match( '#/video-sitemap\.xml$#', $path ) ) {
		spokora_content_video_sitemap();
	}
}
add_action( 'template_redirect', 'spokora_content_sitemaps', 0 );

/**
 * Google News sitemap. Only the last two days.
 */
function spokora_content_news_sitemap() {
	$lang = substr( (string) get_bloginfo( 'language' ), 0, 2 );
	if ( '' === $lang ) {
		$lang = 'en';
	}
	$query = new WP_Query(
		array(
			'post_type'      => spokora_scoring_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'after' => '2 days ago',
				),
			),
			'meta_key'       => '_spokora_content_kind',
			'meta_value'     => 'news',
		)
	);
	status_header( 200 );
	header( 'Content-Type: text/xml; charset=utf-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( get_permalink( $post ) ) . "</loc>\n";
		echo "\t\t<news:news>\n";
		echo "\t\t\t<news:publication><news:name>" . esc_html( get_bloginfo( 'name' ) ) . "</news:name><news:language>" . esc_html( $lang ) . "</news:language></news:publication>\n";
		echo "\t\t\t<news:publication_date>" . esc_html( get_post_time( 'c', true, $post ) ) . "</news:publication_date>\n";
		echo "\t\t\t<news:title>" . esc_html( get_the_title( $post ) ) . "</news:title>\n";
		echo "\t\t</news:news>\n";
		echo "\t</url>\n";
	}
	echo '</urlset>';
	exit;
}

/**
 * Video sitemap for posts and show episodes that have an https video link.
 */
function spokora_content_video_sitemap() {
	$ids = array();
	$marked = get_posts(
		array(
			'post_type'      => spokora_scoring_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_spokora_video_url',
					'value'   => 'https://',
					'compare' => 'LIKE',
				),
			),
		)
	);
	$episodes = get_posts(
		array(
			'post_type'      => 'spokora_episode',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_key'       => '_spokora_episode_kind',
			'meta_value'     => 'video',
		)
	);
	foreach ( array_merge( (array) $marked, (array) $episodes ) as $id ) {
		$ids[ (int) $id ] = (int) $id;
	}
	status_header( 200 );
	header( 'Content-Type: text/xml; charset=utf-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
	foreach ( $ids as $id ) {
		$url = spokora_content_video_url( $id );
		if ( '' === $url ) {
			continue;
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$desc  = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '' );
		$image = get_the_post_thumbnail_url( $post, 'full' );
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( get_permalink( $post ) ) . "</loc>\n";
		echo "\t\t<video:video>\n";
		if ( is_string( $image ) && '' !== $image ) {
			echo "\t\t\t<video:thumbnail_loc>" . esc_url( $image ) . "</video:thumbnail_loc>\n";
		}
		echo "\t\t\t<video:title>" . esc_html( get_the_title( $post ) ) . "</video:title>\n";
		echo "\t\t\t<video:description>" . esc_html( '' !== $desc ? $desc : get_the_title( $post ) ) . "</video:description>\n";
		echo "\t\t\t<video:content_loc>" . esc_url( $url ) . "</video:content_loc>\n";
		echo "\t\t\t<video:publication_date>" . esc_html( get_post_time( 'c', true, $post ) ) . "</video:publication_date>\n";
		echo "\t\t</video:video>\n";
		echo "\t</url>\n";
	}
	echo '</urlset>';
	exit;
}

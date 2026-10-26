<?php
/**
 * Module 7: The Clean XML Sitemap
 * Generates a dynamic sitemap.xml strictly for Pages, Posts, and Custom Post Types.
 * Kills the native WP sitemap to prevent author/tag bloat.
 */

if ( ! defined( 'WPINC' ) ) {
    die( 'Access denied.' );
}

class Spokora_Sitemap_Engine {

    public function __construct() {
        // Disable the bloated default WP sitemap
        add_filter( 'wp_sitemaps_enabled', '__return_false' );
        
        // Intercept requests for sitemap.xml and serve our custom one
        add_action( 'template_redirect', array( $this, 'generate_custom_sitemap' ), 1 );
    }

    public function generate_custom_sitemap() {
        $path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
        if ( preg_match( '#/sitemap\.xml$#', $path ) ) {
            
            status_header( 200 );
            header( 'Content-Type: text/xml; charset=utf-8' );
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            // Get all published posts, pages, partners, and shows
            $args = array(
                'post_type'      => function_exists( 'spokora_editor_metabox_post_types' ) ? spokora_editor_metabox_post_types() : array( 'post', 'page' ),
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            );
            $query = new WP_Query( $args );

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $url = get_permalink();
                    $modified = get_the_modified_time( 'c' );
                    
                    echo "\t<url>\n";
                    echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
                    echo "\t\t<lastmod>" . esc_html( $modified ) . "</lastmod>\n";
                    echo "\t\t<changefreq>weekly</changefreq>\n";
                    echo "\t\t<priority>0.8</priority>\n";
                    echo "\t</url>\n";
                }
            }
            wp_reset_postdata();

            echo '</urlset>';
            exit;
        }
    }
}

// Boot the module
new Spokora_Sitemap_Engine();
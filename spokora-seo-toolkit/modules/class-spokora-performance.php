<?php
/**
 * Module 2: The Performance & Bloat Killer
 * Strips WP core junk, disables emojis, limits revisions, and cleans the head.
 */

if ( ! defined( 'WPINC' ) ) {
    die( 'Access denied. The matrix is closed.' );
}

class Spokora_Performance_Engine {

    public function __construct() {
        // 1. Nuke the Emoji Scripts (Saves HTTP requests)
        add_action( 'init', array( $this, 'disable_emojis' ) );
        
        // 2. Kill Dashicons on the Frontend (Unless admin is logged in)
        add_action( 'wp_enqueue_scripts', array( $this, 'remove_dashicons' ), 100 );
        
        // 3. Classic themes only: drop unused block/FSE CSS. Block themes (Twenty Twenty-Five, etc.)
        // need global-styles + block library or the live site loses theme.json colors and layout.
        add_action( 'wp_enqueue_scripts', array( $this, 'remove_global_styles' ), 9999 );
        add_action( 'init', array( $this, 'kill_inline_fse_styles' ) );
        
        // 4. Force Heartbeat API to slow down (Saves server CPU)
        add_action( 'init', array( $this, 'slow_heartbeat' ), 1 );
    }

    // --- 1. DISABLE EMOJIS ---
    public function disable_emojis() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' ); 
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' ); 
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        // Remove from TinyMCE
        add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
        // Remove DNS prefetch
        add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_remove_dns_prefetch' ), 10, 2 );
    }

    public function disable_emojis_tinymce( $plugins ) {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, array( 'wpemoji' ) );
        } else {
            return array();
        }
    }

    public function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
        if ( 'dns-prefetch' !== $relation_type || ! is_array( $urls ) ) {
            return $urls;
        }
        return array_values(
            array_filter(
                $urls,
                static function ( $url ) {
                    return false === strpos( (string) $url, '/images/core/emoji/' );
                }
            )
        );
    }

    // --- 2. REMOVE DASHICONS ---
    public function remove_dashicons() {
        if ( ! is_user_logged_in() ) {
            wp_deregister_style( 'dashicons' );
        }
    }

    /**
     * Block themes and any theme.json theme need core global styles on the front.
     * Classic themes without theme.json can still drop that CSS as bloat.
     *
     * @return bool
     */
    private function theme_needs_global_styles() {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            return true;
        }
        if ( function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json() ) {
            return true;
        }
        return false;
    }

    public function kill_inline_fse_styles() {
        if ( $this->theme_needs_global_styles() ) {
            return;
        }
        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
        remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
    }

    public function remove_global_styles() {
        if ( $this->theme_needs_global_styles() ) {
            return;
        }
        wp_dequeue_style( 'global-styles' );
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wc-blocks-style' );
    }

    // --- 4. SLOW THE HEARTBEAT (SERVER SAVER) ---
    public function slow_heartbeat() {
        // Heartbeat pings the server constantly while in the admin dashboard. We slow it down to save CPU.
        add_filter( 'heartbeat_settings', function( $settings ) {
            $settings['interval'] = 60; // 60 seconds instead of 15
            return $settings;
        });
    }
}

// Boot the module
new Spokora_Performance_Engine();
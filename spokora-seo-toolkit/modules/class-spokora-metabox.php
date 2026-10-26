<?php
/**
 * Module 5: The Page-Level SEO Meta Box
 * Injects a custom SEO panel into the WordPress editor (Posts & Pages).
 */

if ( ! defined( 'WPINC' ) ) {
    die( 'Access denied.' );
}

class Spokora_MetaBox_Engine {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_seo_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_seo_meta_data' ) );
    }

    public function add_seo_meta_box() {
        $screens = spokora_scoring_post_types();
        foreach ( $screens as $screen ) {
            add_meta_box(
                'spokora_seo_meta_box',
                __( 'SEO', 'spokora-seo-toolkit' ),
                array( $this, 'render_meta_box' ),
                $screen,
                'normal',
                'default'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'spokora_seo_save_meta', 'spokora_seo_meta_nonce' );

        if ( function_exists( 'spokora_render_post_keyword_fields' ) ) {
            spokora_render_post_keyword_fields( $post );
        }

        if ( function_exists( 'spokora_is_module_enabled' ) && spokora_is_module_enabled( 'seo_score' ) ) {
            do_action( 'spokora_render_seo_score_panel', $post );
        }

        $seo_title     = get_post_meta( $post->ID, '_spokora_seo_title', true );
        $seo_desc      = get_post_meta( $post->ID, '_spokora_seo_desc', true );
        $custom_schema = get_post_meta( $post->ID, '_spokora_custom_schema', true );

        echo '<p style="color: #666;">' . esc_html__( 'Override global fallbacks for this page.', 'spokora-seo-toolkit' ) . '</p>';

        echo '<label for="spokora_seo_title" style="font-weight: bold; display: block; margin-top: 15px;">' . esc_html__( 'Custom SEO Title', 'spokora-seo-toolkit' ) . '</label>';
        echo '<input type="text" id="spokora_seo_title" name="spokora_seo_title" value="' . esc_attr( $seo_title ) . '" style="width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px;" placeholder="' . esc_attr__( 'Max 60 characters…', 'spokora-seo-toolkit' ) . '" />';
        echo '<div id="spokora-seo-title-count" class="spokora-seo-char-count">0/60</div>';

        echo '<label for="spokora_seo_desc" style="font-weight: bold; display: block; margin-top: 15px;">' . esc_html__( 'Custom Meta Description', 'spokora-seo-toolkit' ) . '</label>';
        echo '<textarea id="spokora_seo_desc" name="spokora_seo_desc" rows="3" style="width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px;" placeholder="' . esc_attr__( 'Max 160 characters…', 'spokora-seo-toolkit' ) . '">' . esc_textarea( $seo_desc ) . '</textarea>';
        echo '<div id="spokora-seo-desc-count" class="spokora-seo-char-count">0/160</div>';

        if ( function_exists( 'spokora_is_module_enabled' ) && spokora_is_module_enabled( 'seo_score' ) ) {
            do_action( 'spokora_after_seo_fields', $post );
        }

        if ( function_exists( 'spokora_is_module_enabled' ) && spokora_is_module_enabled( 'rank_tracker' ) && function_exists( 'spokora_dfs_is_configured' ) && spokora_dfs_is_configured() ) {
            $watchlist = get_post_meta( $post->ID, '_spokora_visibility_watchlist', true );
            $track     = get_post_meta( $post->ID, '_spokora_track_visibility', true );
            echo '<hr style="margin: 20px 0;">';
            echo '<h4 style="margin:0 0 10px;">' . esc_html__( 'Market Visibility', 'spokora-seo-toolkit' ) . '</h4>';
            echo '<p style="font-size:12px;color:#666;">' . esc_html__( 'When enabled, all keyphrases above are tracked for this page in Market Visibility.', 'spokora-seo-toolkit' ) . '</p>';
            echo '<label for="spokora_visibility_watchlist" style="font-weight:bold;display:block;margin-top:12px;">' . esc_html__( 'Watchlist', 'spokora-seo-toolkit' ) . '</label>';
            echo '<select id="spokora_visibility_watchlist" name="spokora_visibility_watchlist" style="width:100%;margin-top:5px;">';
            $groups = array(
                ''        => __( '— None —', 'spokora-seo-toolkit' ),
                'bridge'  => __( 'Bridge pages', 'spokora-seo-toolkit' ),
                'local'   => __( 'Local', 'spokora-seo-toolkit' ),
                'brand'   => __( 'Brand', 'spokora-seo-toolkit' ),
                'product' => __( 'Product', 'spokora-seo-toolkit' ),
            );
            foreach ( $groups as $val => $label ) {
                echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) $watchlist, (string) $val, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            echo '<label style="display:block;margin-top:12px;"><input type="checkbox" name="spokora_track_visibility" value="1"' . checked( $track, '1', false ) . ' /> ' . esc_html__( 'Track in Market Visibility', 'spokora-seo-toolkit' ) . '</label>';
        }

        $kind     = (string) get_post_meta( $post->ID, '_spokora_content_kind', true );
        $video    = (string) get_post_meta( $post->ID, '_spokora_video_url', true );
        $seconds  = (string) get_post_meta( $post->ID, '_spokora_video_seconds', true );
        echo '<hr style="margin: 20px 0;">';
        echo '<label for="spokora_content_kind" style="font-weight:bold;display:block;">' . esc_html__( 'What is this?', 'spokora-seo-toolkit' ) . '</label>';
        echo '<select id="spokora_content_kind" name="spokora_content_kind" style="margin-top:5px;">';
        foreach ( array(
            ''        => __( 'Ordinary page or post', 'spokora-seo-toolkit' ),
            'article' => __( 'Article', 'spokora-seo-toolkit' ),
            'news'    => __( 'News article', 'spokora-seo-toolkit' ),
            'video'   => __( 'Video', 'spokora-seo-toolkit' ),
        ) as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $kind, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p style="font-size:12px;color:#666;">' . esc_html__( 'News articles from the last two days are listed in the news sitemap. A video link is listed in the video sitemap and marked up for search.', 'spokora-seo-toolkit' ) . '</p>';
        echo '<label for="spokora_video_url" style="font-weight:bold;display:block;margin-top:12px;">' . esc_html__( 'Video file or watch page', 'spokora-seo-toolkit' ) . '</label>';
        echo '<input type="url" id="spokora_video_url" name="spokora_video_url" value="' . esc_attr( $video ) . '" style="width:100%;padding:8px;margin-top:5px;" placeholder="https://" />';
        echo '<label for="spokora_video_seconds" style="font-weight:bold;display:block;margin-top:12px;">' . esc_html__( 'Length in seconds', 'spokora-seo-toolkit' ) . '</label>';
        echo '<input type="number" min="0" id="spokora_video_seconds" name="spokora_video_seconds" value="' . esc_attr( $seconds ) . '" style="width:8rem;padding:8px;margin-top:5px;" />';

        echo '<hr style="margin: 20px 0;">';
        echo '<label for="spokora_custom_schema" style="font-weight: bold; display: block; color: #0056D2;">' . esc_html__( 'Custom JSON-LD Schema (Optional)', 'spokora-seo-toolkit' ) . '</label>';
        echo '<p style="font-size: 12px; color: #666;">' . esc_html__( 'Choose a starter below or paste raw JSON-LD to override the dynamic schema generator for this page.', 'spokora-seo-toolkit' ) . '</p>';
        if ( function_exists( 'spokora_render_json_ld_preset_field' ) ) {
            spokora_render_json_ld_preset_field( 'spokora_custom_schema', (int) $post->ID, 'spokora_schema_json_preset' );
        }
        echo '<textarea id="spokora_custom_schema" name="spokora_custom_schema" rows="10" style="width: 100%; padding: 12px; margin-top: 5px; border-radius: 4px; font-family: monospace; background: #121212; color: #00DDBB; white-space: pre; overflow-wrap: normal; overflow-x: scroll;">' . esc_textarea( $custom_schema ) . '</textarea>';
    }

    public function save_seo_meta_data( $post_id ) {
        $nonce = isset( $_POST['spokora_seo_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spokora_seo_meta_nonce'] ) ) : '';
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'spokora_seo_save_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['spokora_content_kind'] ) ) {
            $kind = sanitize_key( wp_unslash( $_POST['spokora_content_kind'] ) );
            if ( ! in_array( $kind, array( '', 'article', 'news', 'video' ), true ) ) {
                $kind = '';
            }
            update_post_meta( $post_id, '_spokora_content_kind', $kind );
        }
        if ( isset( $_POST['spokora_video_url'] ) ) {
            $video = esc_url_raw( wp_unslash( $_POST['spokora_video_url'] ) );
            if ( '' !== $video && 0 !== strpos( $video, 'https://' ) ) {
                $video = '';
            }
            update_post_meta( $post_id, '_spokora_video_url', $video );
        }
        if ( isset( $_POST['spokora_video_seconds'] ) ) {
            $seconds = absint( $_POST['spokora_video_seconds'] );
            if ( $seconds > 0 ) {
                update_post_meta( $post_id, '_spokora_video_seconds', $seconds );
            } else {
                delete_post_meta( $post_id, '_spokora_video_seconds' );
            }
        }

        if ( isset( $_POST['spokora_seo_title'] ) ) {
            update_post_meta( $post_id, '_spokora_seo_title', sanitize_text_field( wp_unslash( $_POST['spokora_seo_title'] ) ) );
        }
        if ( isset( $_POST['spokora_seo_desc'] ) ) {
            update_post_meta( $post_id, '_spokora_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['spokora_seo_desc'] ) ) );
        }

        if ( isset( $_POST['spokora_custom_schema'] ) ) {
            $schema_data = wp_strip_all_tags( wp_unslash( $_POST['spokora_custom_schema'] ) );
            update_post_meta( $post_id, '_spokora_custom_schema', $schema_data );
        }

        if ( function_exists( 'spokora_save_post_keywords_from_request' ) ) {
            $saved = spokora_save_post_keywords_from_request( (int) $post_id );
        } else {
            $saved = array( 'all' => array() );
        }

        if ( function_exists( 'spokora_is_module_enabled' ) && spokora_is_module_enabled( 'rank_tracker' ) && function_exists( 'spokora_dfs_sync_keywords_for_post' ) ) {
            $track = ! empty( $_POST['spokora_track_visibility'] );
            $list  = isset( $_POST['spokora_visibility_watchlist'] ) ? sanitize_key( wp_unslash( $_POST['spokora_visibility_watchlist'] ) ) : '';

            if ( $track && ! empty( $saved['all'] ) ) {
                spokora_dfs_sync_keywords_for_post( (int) $post_id, $saved['all'], $list, true );
            } elseif ( ! $track && function_exists( 'spokora_dfs_remove_keyword_for_post' ) ) {
                spokora_dfs_remove_keyword_for_post( (int) $post_id );
            } elseif ( ! empty( $saved['all'] ) ) {
                update_post_meta( $post_id, '_spokora_visibility_watchlist', $list );
            }
        }
    }
}

new Spokora_MetaBox_Engine();

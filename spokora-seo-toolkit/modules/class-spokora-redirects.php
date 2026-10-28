<?php
/**
 * Module 9: The Redirect Engine
 * Intercepts traffic and applies 301 redirects based on agency input.
 */

if ( ! defined( 'WPINC' ) ) {
    die( 'Access denied.' );
}

class Spokora_Redirect_Engine {

    /**
     * Pre-update permalink paths keyed by post ID.
     *
     * @var array<int, string>
     */
    private $old_paths = array();

    public function __construct() {
        // Register the Sub-Menu and Settings
        add_action( 'admin_menu', array( $this, 'add_redirect_menu' ) );
        add_action( 'admin_init', array( $this, 'register_redirect_settings' ) );
        add_action( 'admin_init', array( $this, 'import_legacy_redirects' ) );

        // Automatic 301 creation when a published post or page changes its URL slug
        add_action( 'pre_post_update', array( $this, 'remember_old_permalink' ), 10, 2 );
        add_action( 'post_updated', array( $this, 'catch_slug_change_redirect' ), 10, 3 );
        
        // The Traffic Interceptor (Fires before the page loads)
        add_action( 'template_redirect', array( $this, 'process_301_redirects' ), 1 );
    }

    /**
     * Capture the existing public path before WordPress updates a published post or page.
     *
     * @param int   $post_id Post ID.
     * @param array $data    New post data.
     */
    public function remember_old_permalink( $post_id, $data ) {
        unset( $data );
        $post_id = (int) $post_id;
        if ( $post_id <= 0 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
            return;
        }
        if ( ! function_exists( 'is_post_type_viewable' ) || ! is_post_type_viewable( $post->post_type ) ) {
            return;
        }
        $permalink = get_permalink( $post_id );
        if ( ! is_string( $permalink ) || '' === $permalink ) {
            return;
        }
        $path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
        if ( '' !== $path && '/' !== $path ) {
            $this->old_paths[ $post_id ] = '/' . ltrim( $path, '/' );
        }
    }

    /**
     * Append a 301 redirect automatically when a published item's path changes.
     *
     * @param int     $post_id     Post ID.
     * @param WP_Post $post_after  Updated post object.
     * @param WP_Post $post_before Previous post object.
     */
    public function catch_slug_change_redirect( $post_id, $post_after, $post_before ) {
        $post_id = (int) $post_id;
        if ( ! isset( $this->old_paths[ $post_id ] ) ) {
            return;
        }
        $old_path = $this->old_paths[ $post_id ];
        unset( $this->old_paths[ $post_id ] );

        if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
            return;
        }
        if ( 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status ) {
            return;
        }
        if ( $post_before->post_name === $post_after->post_name && (int) $post_before->post_parent === (int) $post_after->post_parent ) {
            return;
        }
        $new_permalink = get_permalink( $post_id );
        if ( ! is_string( $new_permalink ) || '' === $new_permalink ) {
            return;
        }
        $new_path = (string) wp_parse_url( $new_permalink, PHP_URL_PATH );
        if ( '' === $new_path || '/' === $new_path ) {
            return;
        }
        $new_path = '/' . ltrim( $new_path, '/' );
        if ( untrailingslashit( $old_path ) === untrailingslashit( $new_path ) ) {
            return;
        }

        $raw   = (string) get_option( 'spokora_redirect_rules', '' );
        $lines = preg_split( '/\r\n|\r|\n/', $raw );
        $kept  = array();
        $found = false;

        if ( is_array( $lines ) ) {
            foreach ( $lines as $line ) {
                $line = trim( (string) $line );
                if ( '' === $line || false === strpos( $line, '|' ) ) {
                    continue;
                }
                list( $from, $to ) = array_map( 'trim', explode( '|', $line, 2 ) );
                // Drop reverse rule (/new/ -> /old/) to avoid redirect loops if slug was reverted.
                if ( untrailingslashit( $from ) === untrailingslashit( $new_path ) ) {
                    continue;
                }
                // Update existing rule or chain pointing to the old path so it points straight to the new path.
                if ( untrailingslashit( $from ) === untrailingslashit( $old_path ) ) {
                    $kept[] = $old_path . ' | ' . $new_path;
                    $found  = true;
                    continue;
                }
                if ( untrailingslashit( $to ) === untrailingslashit( $old_path ) ) {
                    $kept[] = $from . ' | ' . $new_path;
                    continue;
                }
                $kept[] = $from . ' | ' . $to;
            }
        }
        if ( ! $found ) {
            $kept[] = $old_path . ' | ' . $new_path;
        }
        update_option( 'spokora_redirect_rules', implode( "\n", $kept ) );
    }

    // --- 1. THE SUB-MENU ---
    public function add_redirect_menu() {
        add_submenu_page(
            spokora_admin_menu_slug(),           // Parent slug
            '301 Redirects',              // Page title
            'Redirects',                  // Menu title
            'manage_options',             // Capability
            'spokora-redirects',          // Menu slug
            array( $this, 'render_redirect_ui' ) // Output function
        );
    }

    // --- 2. REGISTER THE DATABASE FIELD ---
    public function register_redirect_settings() {
        register_setting(
            'spokora_redirect_group',
            'spokora_redirect_rules',
            array(
                'sanitize_callback' => array( $this, 'sanitize_redirect_rules' ),
            )
        );
    }

    public function sanitize_redirect_rules( $raw ) {
        if ( ! is_string( $raw ) ) {
            return '';
        }
        $lines = preg_split( '/\r\n|\r|\n/', wp_unslash( $raw ) );
        if ( ! is_array( $lines ) ) {
            return '';
        }
        $clean = array();
        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line || false === strpos( $line, '|' ) ) {
                continue;
            }
            $parts = explode( '|', $line, 2 );
            $old   = trim( (string) $parts[0] );
            $new   = trim( (string) $parts[1] );
            if ( '' === $old || '' === $new ) {
                continue;
            }
            // Disallow protocol-relative targets like //evil.com that bypass scheme checks.
            if ( 0 === strpos( $new, '//' ) ) {
                continue;
            }
            if ( 0 === strpos( $new, 'http://' ) || 0 === strpos( $new, 'https://' ) ) {
                $new = esc_url_raw( $new );
            } else {
                $new = '/' . ltrim( sanitize_text_field( $new ), '/' );
            }
            if ( 0 === strpos( $old, 'http://' ) || 0 === strpos( $old, 'https://' ) ) {
                $old = esc_url_raw( $old );
            } else {
                $old = '/' . ltrim( sanitize_text_field( $old ), '/' );
            }
            if ( '' === $old || '' === $new || untrailingslashit( $old ) === untrailingslashit( $new ) ) {
                continue;
            }
            $clean[] = $old . ' | ' . $new;
        }
        return implode( "\n", $clean );
    }

    public function import_legacy_redirects() {
        if ( ! isset( $_POST['spokora_import_legacy_redirects'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! check_admin_referer( 'spokora_import_legacy_redirects', 'spokora_import_legacy_redirects_nonce' ) ) {
            return;
        }
        $result = spokora_import_legacy_redirects();
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'            => 'spokora-redirects',
                    'redirects'       => (int) $result['added'],
                    'redirects_left'  => (int) $result['skipped'],
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    // --- 3. THE TRAFFIC INTERCEPTOR ---
    public function process_301_redirects() {
        if ( is_admin() ) return; // Don't redirect the dashboard

        $current_path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
        if ( is_string( $current_path ) ) {
            $current_path = '/' . ltrim( $current_path, '/' );
            if ( '/llms.txt' === $current_path || preg_match( '#^/ai-docs/.+\.md$#', $current_path ) || preg_match( '#^/md/.+\.md$#', $current_path ) ) {
                return;
            }
        }

        $rules = get_option( 'spokora_redirect_rules' );
        if ( empty( $rules ) || ! is_string( $rules ) ) return;

        $request_uri  = function_exists( 'spokora_request_uri' ) ? spokora_request_uri() : '/';
        $current_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
        if ( '' === $current_path ) {
            $current_path = '/';
        }
        $current_url = home_url( $current_path );

        // Break the saved rules into an array (line by line)
        $lines = explode( "\n", str_replace( "\r", "", $rules ) );

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || strpos( $line, '|' ) === false ) continue;

            // Split the old URL and the new URL
            list( $old_url, $new_url ) = explode( '|', $line, 2 );
            $old_url = trim( $old_url );
            $new_url = trim( $new_url );
            if ( '' === $old_url || '' === $new_url || 0 === strpos( $new_url, '//' ) ) {
                continue;
            }

            // Check if the current URL or Path matches the old URL
            if ( $current_url === $old_url || $current_path === $old_url || trailingslashit( $current_path ) === trailingslashit( $old_url ) ) {
                
                // If it's a relative path, make it an absolute URL for safety
                if ( 0 !== strpos( $new_url, 'http://' ) && 0 !== strpos( $new_url, 'https://' ) ) {
                    $new_url = home_url( '/' . ltrim( $new_url, '/' ) );
                }

                $target = esc_url_raw( $new_url );
                if ( '' === $target || untrailingslashit( $target ) === untrailingslashit( $current_url ) ) {
                    continue;
                }

                // Boom. Reroute the traffic permanently.
                wp_redirect( $target, 301 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- intentional 301 rules configured by site administrator.
                exit;
            }
        }
    }

    // --- 4. THE AGENCY UI ---
    public function render_redirect_ui() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $rules = get_option( 'spokora_redirect_rules' );
        ?>
        <div class="wrap" style="max-width: 800px; margin-top: 20px;">
            <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-top: 4px solid #FF7F00;">
                
                <h1 style="margin-top: 0; font-weight: 800;">Redirects</h1>
                <p style="color: #666; font-size: 16px;">Traffic routing and link equity preservation.</p>
                <?php if ( isset( $_GET['redirects'] ) ) : ?>
                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 16px 0; border-left: 4px solid #28a745;">
                        <?php echo esc_html( spokora_legacy_redirect_result_sentence() ); ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="" style="margin: 16px 0 0;">
                    <?php wp_nonce_field( 'spokora_import_legacy_redirects', 'spokora_import_legacy_redirects_nonce' ); ?>
                    <input type="hidden" name="spokora_import_legacy_redirects" value="1" />
                    <button type="submit" class="button">Bring in redirects from Yoast, Rank Math, AIOSEO, or SEOPress</button>
                    <p style="font-size: 13px; color: #555; margin: 8px 0 0;">Exact permanent redirects only. A redirect already in this list is left as it is. Pattern and temporary redirects are not copied. Import SEO and turning those plugins off do this too.</p>
                </form>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                <form method="post" action="options.php">
                    <?php settings_fields( 'spokora_redirect_group' ); ?>
                    
                    <label style="font-weight: 700; font-size: 14px; display: block; margin-bottom: 10px;">Rapid Entry Matrix</label>
                    <p style="font-size: 13px; color: #555; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                        <strong>Format:</strong> <code>/old-path/ | https://yourdomain.com/new-path/</code><br>
                        Enter one redirect per line. Separate the old URL and the new URL with a pipe character ( <strong>|</strong> ). When a published post or page changes its URL slug, Spokora automatically adds the 301 redirect here.
                    </p>

                    <textarea name="spokora_redirect_rules" class="spokora-code-field" rows="15" placeholder="/about-us/ | /about/&#10;/old-services/ | /services/"><?php echo esc_textarea( $rules ); ?></textarea>

                    <div style="margin-top: 20px;">
                        <?php submit_button( 'Save Routing Rules', 'primary', 'submit', false, array( 'style' => 'background: #FF7F00; border-color: #FF7F00; text-shadow: none; font-weight: bold;' ) ); ?>
                    </div>
                </form>

            </div>
        </div>
        <?php
    }
}

// Boot the module
new Spokora_Redirect_Engine();
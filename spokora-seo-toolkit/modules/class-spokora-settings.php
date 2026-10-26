<?php
/**
 * Module 4: The Settings UI & Data Hijacker
 * Creates the Spokora dashboard and migrates competitor SEO data.
 */

if ( ! defined( 'WPINC' ) ) {
    die( 'Access denied.' );
}

class Spokora_Settings_Engine {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_core_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_core_settings' ) );
        add_action( 'admin_init', array( $this, 'process_seo_migration' ) ); // The Hijack Listener
        add_action( 'admin_init', array( $this, 'process_legacy_seo_deactivation' ) );
    }

    public function add_core_menu_page() {
        add_submenu_page(
            spokora_admin_menu_slug(),
            __( 'Business Info', 'spokora-seo-toolkit' ),
            __( 'Business Info', 'spokora-seo-toolkit' ),
            'manage_options',
            spokora_settings_page_slug(),
            array( $this, 'render_settings_page' )
        );
    }

    public function register_core_settings() {
        register_setting(
            'spokora_settings_group',
            'spokora_options',
            array(
                'sanitize_callback' => 'spokora_sanitize_spokora_options',
            )
        );
    }

    // --- THE HIJACK PROTOCOL ---
    public function process_seo_migration() {
        if ( isset( $_POST['spokora_run_migration'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'spokora_run_migration_action', 'spokora_migration_nonce' ) ) {
            $migrated_count = 0;
            $paged          = 1;
            $batch_size     = 500;

            do {
                $post_ids = get_posts(
                    array(
                        'post_type'              => 'any',
                        'post_status'            => 'any',
                        'posts_per_page'         => $batch_size,
                        'paged'                  => $paged,
                        'fields'                 => 'ids',
                        'orderby'                => 'ID',
                        'order'                  => 'ASC',
                        'no_found_rows'          => true,
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                    )
                );
                if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
                    break;
                }

                foreach ( $post_ids as $post_id ) {
                    $post_id      = (int) $post_id;
                    $yoast_title  = get_post_meta( $post_id, '_yoast_wpseo_title', true );
                    $yoast_desc   = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
                    $rm_title     = get_post_meta( $post_id, 'rank_math_title', true );
                    $rm_desc      = get_post_meta( $post_id, 'rank_math_description', true );
                    $aioseo_title = get_post_meta( $post_id, '_aioseo_title', true );
                    $aioseo_desc  = get_post_meta( $post_id, '_aioseo_description', true );
                    $sp_title     = get_post_meta( $post_id, '_seopress_titles_title', true );
                    $sp_desc      = get_post_meta( $post_id, '_seopress_titles_desc', true );

                    $hijacked_title = ! empty( $rm_title ) ? $rm_title : ( ! empty( $yoast_title ) ? $yoast_title : ( ! empty( $aioseo_title ) ? $aioseo_title : $sp_title ) );
                    $hijacked_desc  = ! empty( $rm_desc ) ? $rm_desc : ( ! empty( $yoast_desc ) ? $yoast_desc : ( ! empty( $aioseo_desc ) ? $aioseo_desc : $sp_desc ) );

                    if ( ! empty( $hijacked_title ) ) {
                        update_post_meta( $post_id, '_spokora_seo_title', sanitize_text_field( $hijacked_title ) );
                        ++$migrated_count;
                    }
                    if ( ! empty( $hijacked_desc ) ) {
                        update_post_meta( $post_id, '_spokora_seo_desc', sanitize_textarea_field( $hijacked_desc ) );
                        ++$migrated_count;
                    }
                    if ( function_exists( 'spokora_hijack_legacy_keywords_for_post' ) ) {
                        $migrated_count += spokora_hijack_legacy_keywords_for_post( $post_id );
                    }
                }

                ++$paged;
            } while ( count( $post_ids ) === $batch_size && $paged <= 100 );

            $redirects = spokora_import_legacy_redirects();
            $args      = array(
                'page'           => 'spokora-legacy-import',
                'migration'      => 'success',
                'count'          => $migrated_count,
                'redirects'      => (int) $redirects['added'],
                'redirects_left' => (int) $redirects['skipped'],
            );
            if ( ! empty( $_POST['spokora_setup_return'] ) ) {
                $args['page'] = 'spokora-setup';
                $args['step'] = isset( $_POST['spokora_setup_step'] ) ? max( 1, absint( wp_unslash( $_POST['spokora_setup_step'] ) ) ) : 2;
            }
            wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    public function process_legacy_seo_deactivation() {
        if ( ! isset( $_POST['spokora_deactivate_legacy_seo'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! check_admin_referer( 'spokora_deactivate_legacy_seo_action', 'spokora_legacy_seo_nonce' ) ) {
            return;
        }
        if ( empty( $_POST['spokora_legacy_import_confirmed'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=spokora-legacy-import&legacy_seo=confirm_required' ) );
            exit;
        }
        $selected  = isset( $_POST['spokora_legacy_plugins'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['spokora_legacy_plugins'] ) ) : array();
        $redirects = spokora_import_legacy_redirects();
        $result    = spokora_deactivate_legacy_seo_plugins( $selected );
        $args      = array(
            'legacy_seo'      => 'deactivated',
            'count'           => count( $result['deactivated'] ),
            'redirects'       => (int) $redirects['added'],
            'redirects_left'  => (int) $redirects['skipped'],
        );
        if ( $result['failed'] ) {
            $args['legacy_failed'] = rawurlencode( implode( ', ', $result['failed'] ) );
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=spokora-legacy-import' ) ) );
        exit;
    }

    // --- RENDER THE UI ---
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $options = get_option( 'spokora_options' );
        if ( ! is_array( $options ) ) $options = array();
        ?>
		<div class="wrap" style="max-width: 800px; margin-top: 20px;">
            <?php spokora_render_subnav( 'settings' ); ?>
            <?php spokora_maybe_render_gemini_vault_hint(); ?>
            <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-top: 4px solid #0056D2;">
                
                <h1 style="margin-top: 0; font-weight: 800; letter-spacing: -0.5px;"><?php echo esc_html__( 'Business Info', 'spokora-seo-toolkit' ); ?></h1>
                <p style="color: #666; font-size: 16px;"><?php echo esc_html__( 'Organization schema, global SEO fallbacks, and site identity. Legacy plugin import lives under Configure → Import SEO.', 'spokora-seo-toolkit' ); ?></p>
                
                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin flash messages after verified POST redirects.
                if ( isset( $_GET['migration'] ) && 'success' === $_GET['migration'] ) {
                    $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
                    echo '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #28a745;"><strong>Success:</strong> Imported ' . esc_html( (string) $count ) . ' SEO data points from legacy plugins. ' . esc_html( spokora_legacy_redirect_result_sentence() ) . '</div>';
                }
                if ( isset( $_GET['legacy_seo'] ) && 'deactivated' === $_GET['legacy_seo'] ) {
                    $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
                    echo '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #28a745;"><strong>Success:</strong> Deactivated ' . esc_html( (string) $count ) . ' legacy SEO plugin(s). ' . esc_html( spokora_legacy_redirect_result_sentence() ) . '</div>';
                    if ( ! empty( $_GET['legacy_failed'] ) ) {
                        echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #dc3545;"><strong>Warning:</strong> Could not deactivate: ' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['legacy_failed'] ) ) ) ) . '</div>';
                    }
                }
                if ( isset( $_GET['legacy_seo'] ) && 'confirm_required' === $_GET['legacy_seo'] ) {
                    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #dc3545;">Check the confirmation box: import or verify meta in Spokora before deactivating legacy SEO plugins.</div>';
                }
                $legacy_active = spokora_detect_active_legacy_seo_plugins();
                if ( $legacy_active ) {
                    echo '<div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #ffc107;"><strong>Legacy SEO detected:</strong> ';
                    echo esc_html( implode( ', ', array_values( $legacy_active ) ) );
                    echo '. Run <strong>Import legacy SEO data</strong> on Configure → Import SEO, then deactivate when ready.</div>';
                }
                ?>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                <form method="post" action="options.php">
                    <?php settings_fields( 'spokora_settings_group' ); ?>

                    <h2 id="spokora-agency-roi" style="font-weight: 700; margin-top: 30px;"><?php echo esc_html__( 'Agency', 'spokora-seo-toolkit' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__( 'Agency ROI on dashboard', 'spokora-seo-toolkit' ); ?></th>
                            <td>
                                <input type="hidden" name="spokora_options[agency_roi_dashboard]" value="0" />
                                <label>
                                    <input type="checkbox" name="spokora_options[agency_roi_dashboard]" value="1" <?php checked( ! isset( $options['agency_roi_dashboard'] ) || '1' === (string) ( $options['agency_roi_dashboard'] ?? '1' ) ); ?> />
                                    <?php echo esc_html__( 'Show the Agency ROI snapshot at the top of Spokora → Dashboard (requires Agency admin / ROI module).', 'spokora-seo-toolkit' ); ?>
                                </label>
                                <p class="description"><?php echo esc_html__( 'Hidden from the main WordPress dashboard. Client sites can leave this off; agencies use it for retainer proof.', 'spokora-seo-toolkit' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h2 style="font-weight: 700; margin-top: 30px;">Global SEO Fallbacks</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Front-end SEO output</th>
                            <td>
                                <input type="hidden" name="spokora_options[seo_output]" value="0" />
                                <label>
                                    <input type="checkbox" name="spokora_options[seo_output]" value="1" <?php checked( ! isset( $options['seo_output'] ) || '0' !== (string) ( $options['seo_output'] ?? '1' ) ); ?> />
                                    Spokora outputs <code>&lt;title&gt;</code>, meta description, and JSON-LD on the front end
                                </label>
                                <p class="description" style="margin-top: 8px;">Uncheck when your theme already handles SEO (e.g. bundled HTML themes). Editor meta and LLMS docs are unaffected. Developers: <code>add_filter( 'spokora_should_output_seo', '__return_false' );</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Global Meta Description</th>
                            <td><textarea name="spokora_options[global_desc]" rows="3" style="width: 100%;"><?php echo esc_textarea( $options['global_desc'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Default OpenGraph Image</th>
                            <td><input type="text" name="spokora_options[og_image]" value="<?php echo esc_attr( $options['og_image'] ?? '' ); ?>" style="width: 100%;" /></td>
                        </tr>
                    </table>

                    <h2 style="font-weight: 700; margin-top: 40px;">Home Page SEO (Front Page Only)</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Home Page Meta Title</th>
                            <td><input type="text" name="spokora_options[home_title]" value="<?php echo esc_attr( $options['home_title'] ?? '' ); ?>" style="width: 100%;" placeholder="Your Site // Tagline" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Home Page Meta Description</th>
                            <td><textarea name="spokora_options[home_desc]" rows="3" style="width: 100%;"><?php echo esc_textarea( $options['home_desc'] ?? '' ); ?></textarea></td>
                        </tr>
                    </table>

                    <h2 style="font-weight: 700; margin-top: 40px;">Advanced Entity Schema</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Business type', 'spokora-seo-toolkit' ); ?></th>
                            <td>
                                <?php
                                if ( function_exists( 'spokora_render_schema_type_select' ) ) {
                                    spokora_render_schema_type_select(
                                        isset( $options['schema_type'] ) ? (string) $options['schema_type'] : 'ProfessionalService',
                                        array(
                                            'name'        => 'spokora_options[schema_type]',
                                            'id'          => 'spokora_schema_type',
                                            'style'       => 'width: 100%; max-width: 36em;',
                                        )
                                    );
                                }
                                ?>
                                <p class="description"><?php esc_html_e( 'Maps to schema.org @type on your global Organization JSON-LD. Pick the closest match (agency, SaaS, restaurant, clinic, etc.).', 'spokora-seo-toolkit' ); ?></p>
                            </td>
                        </tr>
                        <tr><th scope="row">Organization Name</th><td><input type="text" name="spokora_options[org_name]" value="<?php echo esc_attr( $options['org_name'] ?? '' ); ?>" style="width: 100%;" placeholder="Your organization" /></td></tr>
                        <tr><th scope="row">Logo URL (512x512)</th><td><input type="url" name="spokora_options[logo_url]" value="<?php echo esc_attr( $options['logo_url'] ?? '' ); ?>" style="width: 100%;" /></td></tr>
                        <tr><th scope="row">Public Email</th><td><input type="email" name="spokora_options[public_email]" value="<?php echo esc_attr( $options['public_email'] ?? '' ); ?>" style="width: 100%;" /></td></tr>
                        <tr><th scope="row">Phone Number</th><td><input type="text" name="spokora_options[schema_phone]" value="<?php echo esc_attr( $options['schema_phone'] ?? '' ); ?>" style="width: 100%;" /></td></tr>
                        <tr>
                            <th scope="row">Address (Street & City)</th>
                            <td>
                                <input type="text" name="spokora_options[schema_street]" value="<?php echo esc_attr( $options['schema_street'] ?? '' ); ?>" style="width: 100%; margin-bottom: 8px;" placeholder="Street Address" />
                                <input type="text" name="spokora_options[schema_city]" value="<?php echo esc_attr( $options['schema_city'] ?? '' ); ?>" style="width: 100%;" placeholder="City" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">State & Zip</th>
                            <td>
                                <input type="text" name="spokora_options[schema_region]" value="<?php echo esc_attr( $options['schema_region'] ?? '' ); ?>" style="width: 48%;" placeholder="State (e.g. WA)" />
                                <input type="text" name="spokora_options[schema_zip]" value="<?php echo esc_attr( $options['schema_zip'] ?? '' ); ?>" style="width: 48%;" placeholder="Zip Code" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Geo-Coordinates</th>
                            <td>
                                <input type="text" name="spokora_options[latitude]" value="<?php echo esc_attr( $options['latitude'] ?? '' ); ?>" style="width: 48%;" placeholder="Latitude (e.g. 47.2218)" />
                                <input type="text" name="spokora_options[longitude]" value="<?php echo esc_attr( $options['longitude'] ?? '' ); ?>" style="width: 48%;" placeholder="Longitude" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Founder Info</th>
                            <td>
                                <input type="text" name="spokora_options[founder_name]" value="<?php echo esc_attr( $options['founder_name'] ?? '' ); ?>" style="width: 48%;" placeholder="Name" />
                                <input type="text" name="spokora_options[founder_title]" value="<?php echo esc_attr( $options['founder_title'] ?? '' ); ?>" style="width: 48%;" placeholder="Title" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Core Services</th>
                            <td><textarea name="spokora_options[services]" rows="2" style="width: 100%;" placeholder="SEO, Digital Strategy, Market Capture (Comma Separated)"><?php echo esc_textarea( $options['services'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Social Profiles</th>
                            <td><textarea name="spokora_options[socials]" rows="2" style="width: 100%;" placeholder="https://linkedin.com/in/... (Comma Separated)"><?php echo esc_textarea( $options['socials'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">Global JSON-LD import</th>
                            <td>
                                <?php
                                if ( function_exists( 'spokora_render_json_ld_preset_field' ) ) {
                                    spokora_render_json_ld_preset_field( 'spokora_global_schema_json', 0, 'spokora_global_schema_json_preset' );
                                }
                                ?>
                                <textarea id="spokora_global_schema_json" name="spokora_options[global_schema_json]" rows="12" style="width: 100%; font-family: monospace;" placeholder='{ "@context": "https://schema.org", "@graph": [ ... ] }'><?php echo esc_textarea( $options['global_schema_json'] ?? '' ); ?></textarea>
                                <p class="description" style="margin-top: 8px;"><?php esc_html_e( 'Optional. Valid JSON-LD object or array. When set, this replaces the auto-generated Organization/WebSite graph. Per-page Custom JSON-LD in the editor still wins for that URL. Spokora Pro can draft global JSON-LD from Business Info with your model.', 'spokora-seo-toolkit' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 20px;">
                        <?php submit_button( 'Lock In Architecture', 'primary', 'submit', false, array( 'style' => 'background: #0056D2; border-color: #0056D2; text-shadow: none;' ) ); ?>
                    </div>
                </form>

                <p style="margin-top: 24px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-legacy-import' ) ); ?>"><?php echo esc_html__( 'Import SEO from Yoast / Rank Math / AIOSEO', 'spokora-seo-toolkit' ); ?></a>
                    ·
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-media-lab' ) ); ?>"><?php echo esc_html__( 'Media Lab', 'spokora-seo-toolkit' ); ?></a>
                </p>
            </div>
        </div>
        <?php
    }
}

/**
 * Merge submitted Spokora options with existing values and sanitize strings.
 *
 * @param mixed $value Submitted option value.
 * @return array<string, string>
 */
function spokora_sanitize_spokora_options( $value ) {
	$old = get_option( 'spokora_options', array() );
	if ( ! is_array( $old ) ) {
		$old = array();
	}
	$old = spokora_rename_legacy_option_keys( $old );
	if ( ! is_array( $value ) ) {
		return spokora_whitelist_spokora_options( $old );
	}
	$value = spokora_rename_legacy_option_keys( $value );

	$types = spokora_option_field_types();
	$merge = $old;
	foreach ( $types as $key => $type ) {
		if ( ! array_key_exists( $key, $value ) ) {
			continue;
		}
		$submitted = $value[ $key ];
		if ( is_array( $submitted ) || is_object( $submitted ) ) {
			continue;
		}
		$merge[ $key ] = spokora_sanitize_option_field( $type, $submitted );
	}
	if ( function_exists( 'spokora_llms_merge_option_flags' ) ) {
		$merge = spokora_llms_merge_option_flags( $merge, $old, $value );
	}
	if ( function_exists( 'spokora_llms_apply_preamble_guard' ) ) {
		$merge = spokora_llms_apply_preamble_guard( $merge, $old );
	}
	return spokora_whitelist_spokora_options( $merge );
}

/**
 * Keep only known scalar keys.
 *
 * @param array<string, mixed> $options Options.
 * @return array<string, string>
 */
function spokora_whitelist_spokora_options( $options ) {
	$clean = array();
	if ( ! is_array( $options ) ) {
		return $clean;
	}
	foreach ( spokora_option_field_types() as $key => $type ) {
		if ( ! array_key_exists( $key, $options ) ) {
			continue;
		}
		$stored = $options[ $key ];
		if ( is_array( $stored ) || is_object( $stored ) ) {
			continue;
		}
		$clean[ $key ] = spokora_sanitize_option_field( $type, $stored );
	}
	return $clean;
}

new Spokora_Settings_Engine();

require_once SPOKORA_PLUGIN_DIR . 'modules/class-spokora-legacy-import.php';
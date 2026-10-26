<?php
/**
 * Legacy SEO import (hijack + deactivate) — Configure arena, not Business Info.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Access denied.' );
}

class Spokora_Legacy_Import_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 24 );
	}

	public function register_menu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Import SEO', 'spokora-seo-toolkit' ),
			__( 'Import SEO', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-legacy-import',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$legacy_active = function_exists( 'spokora_detect_active_legacy_seo_plugins' ) ? spokora_detect_active_legacy_seo_plugins() : array();
		?>
		<div class="wrap" style="max-width: 800px;">
			<?php spokora_render_subnav( 'legacy-import' ); ?>
			<h1><?php esc_html_e( 'Import SEO', 'spokora-seo-toolkit' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Take over a site from Yoast, Rank Math, AIOSEO, or SEOPress. Copy titles, descriptions, focus and additional keyphrases, and exact 301 redirects into Spokora, then turn the old plugins off.', 'spokora-seo-toolkit' ); ?></p>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin flash messages after verified POST redirects.
			if ( isset( $_GET['migration'] ) && 'success' === $_GET['migration'] ) {
				$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
				$redirect_note = function_exists( 'spokora_legacy_redirect_result_sentence' ) ? spokora_legacy_redirect_result_sentence() : '';
				echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Success:', 'spokora-seo-toolkit' ) . '</strong> ';
				echo esc_html(
					sprintf(
						/* translators: 1: number of meta fields imported, 2: redirect import sentence */
						__( 'Imported %1$d SEO fields from legacy plugins. %2$s', 'spokora-seo-toolkit' ),
						$count,
						$redirect_note
					)
				);
				echo '</p></div>';
			}
			if ( isset( $_GET['legacy_seo'] ) && 'deactivated' === $_GET['legacy_seo'] ) {
				$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
				echo '<div class="notice notice-success"><p>';
				echo esc_html(
					sprintf(
						/* translators: %d: number of plugins deactivated */
						__( 'Deactivated %d legacy SEO plugin(s).', 'spokora-seo-toolkit' ),
						$count
					)
				);
				if ( function_exists( 'spokora_legacy_redirect_result_sentence' ) ) {
					echo ' ' . esc_html( spokora_legacy_redirect_result_sentence() );
				}
				echo '</p></div>';
			}
			if ( isset( $_GET['legacy_seo'] ) && 'confirm_required' === $_GET['legacy_seo'] ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Check the confirmation box: import or verify meta in Spokora before deactivating legacy SEO plugins.', 'spokora-seo-toolkit' ) . '</p></div>';
			}
			if ( $legacy_active ) {
				echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Legacy SEO detected:', 'spokora-seo-toolkit' ) . '</strong> ';
				echo esc_html( implode( ', ', array_values( $legacy_active ) ) );
				echo '</p></div>';
			}
			?>

			<div class="spokora-help__card">
				<h2><?php esc_html_e( 'Import meta and redirects', 'spokora-seo-toolkit' ); ?></h2>
				<p><?php esc_html_e( 'Copies titles, descriptions, primary keyphrases, and additional keyphrases (Rank Math, Yoast Premium, etc.) into Spokora, and copies exact 301 redirects.', 'spokora-seo-toolkit' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'spokora_run_migration_action', 'spokora_migration_nonce' ); ?>
					<input type="hidden" name="spokora_run_migration" value="1" />
					<?php submit_button( __( 'Import legacy SEO data', 'spokora-seo-toolkit' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<?php if ( $legacy_active ) : ?>
			<div class="spokora-help__card">
				<h2><?php esc_html_e( 'Deactivate legacy plugins', 'spokora-seo-toolkit' ); ?></h2>
				<p><?php esc_html_e( 'Imports any missing 301s, then deactivates the selected plugins so titles and schema are not printed twice.', 'spokora-seo-toolkit' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'spokora_deactivate_legacy_seo_action', 'spokora_legacy_seo_nonce' ); ?>
					<input type="hidden" name="spokora_deactivate_legacy_seo" value="1" />
					<fieldset style="border:0;padding:0;margin:0 0 12px;">
						<?php foreach ( $legacy_active as $file => $label ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spokora_legacy_plugins[]" value="<?php echo esc_attr( $file ); ?>" checked />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<label style="display:block;margin-bottom:12px;">
						<input type="checkbox" name="spokora_legacy_import_confirmed" value="1" />
						<?php esc_html_e( 'I have imported or verified meta in Spokora.', 'spokora-seo-toolkit' ); ?>
					</label>
					<?php submit_button( __( 'Deactivate legacy SEO plugins', 'spokora-seo-toolkit' ), 'delete', 'submit', false ); ?>
				</form>
			</div>
			<?php endif; ?>

			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-settings' ) ); ?>"><?php esc_html_e( 'Business Info (schema & fallbacks)', 'spokora-seo-toolkit' ); ?></a></p>
		</div>
		<?php
	}
}

new Spokora_Legacy_Import_Admin();

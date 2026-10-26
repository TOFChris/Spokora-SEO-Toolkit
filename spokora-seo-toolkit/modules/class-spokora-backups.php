<?php
/**
 * One-button site backup for Spokora SEO Toolkit (database + wp-content zip).
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

require_once SPOKORA_PLUGIN_DIR . 'includes/backup-engine.php';

class Spokora_Backups {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 22 );
		add_action( 'admin_post_spokora_backup_download', 'spokora_backup_download' );
	}

	public function admin_menu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Backups', 'spokora-seo-toolkit' ),
			__( 'Backups', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-backups',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap" style="max-width: 40rem;">
			<h1><?php echo esc_html__( 'Backups', 'spokora-seo-toolkit' ); ?></h1>
			<p><?php echo esc_html__( 'One button: download the database and wp-content as a zip. Use this before you import an old SEO plugin. wp-config.php is not in the zip.', 'spokora-seo-toolkit' ); ?></p>
			<?php spokora_free_render_backup_form(); ?>
			<p class="description"><?php echo esc_html__( 'A large media library can take a few minutes. Stay on this page until the file starts.', 'spokora-seo-toolkit' ); ?></p>
		</div>
		<?php
	}
}

/**
 * Shared confirm + download form (Backups screen and Quick setup).
 */
function spokora_free_render_backup_form() {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="spokora_backup_download" />
		<input type="hidden" name="spokora_backup_scrub_secrets" value="1" />
		<?php wp_nonce_field( 'spokora_backup_download' ); ?>
		<p>
			<label>
				<input type="checkbox" name="spokora_backup_confirm" value="1" required />
				<?php echo esc_html__( 'Download the database and wp-content. This includes pages, media, and settings stored in WordPress.', 'spokora-seo-toolkit' ); ?>
			</label>
		</p>
		<p><?php submit_button( __( 'Download backup', 'spokora-seo-toolkit' ), 'secondary', 'submit', false ); ?></p>
	</form>
	<?php
}

new Spokora_Backups();

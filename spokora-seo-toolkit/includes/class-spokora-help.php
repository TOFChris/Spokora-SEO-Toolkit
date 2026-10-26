<?php
/**
 * Spokora SEO Toolkit → Help.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In-plugin help. No API Vault. No Connect-for-support.
 */
class Spokora_Help {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 99 );
	}

	public function register_submenu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Help', 'spokora-seo-toolkit' ),
			__( 'Help', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-help',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$manifest = spokora_get_module_manifest();
		$blurbs   = spokora_get_module_help_blurbs();
		?>
		<div class="wrap" style="max-width:800px;">
			<h1><?php echo esc_html__( 'Spokora SEO Toolkit — Help', 'spokora-seo-toolkit' ); ?></h1>
			<p><?php echo esc_html__( 'This plugin prints titles, schema, a sitemap, exact 301s, and /llms.txt on WordPress websites. It does not need an account or a license key.', 'spokora-seo-toolkit' ); ?></p>
			<h2><?php echo esc_html__( 'Start here', 'spokora-seo-toolkit' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-setup&step=1' ) ); ?>"><?php echo esc_html__( 'Quick setup', 'spokora-seo-toolkit' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-legacy-import' ) ); ?>"><?php echo esc_html__( 'Import SEO from Yoast, Rank Math, AIOSEO, or SEOPress', 'spokora-seo-toolkit' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-redirects' ) ); ?>"><?php echo esc_html__( 'Redirects', 'spokora-seo-toolkit' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-consent' ) ); ?>"><?php echo esc_html__( 'Cookies & privacy', 'spokora-seo-toolkit' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php echo esc_html__( 'Settings → Permalinks → Save (after install)', 'spokora-seo-toolkit' ); ?></a></li>
			</ul>
			<h2><?php echo esc_html__( 'Modules', 'spokora-seo-toolkit' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $manifest as $id => $meta ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $meta['label'] ); ?></strong></td>
							<td><?php echo esc_html( isset( $blurbs[ $id ]['summary'] ) ? $blurbs[ $id ]['summary'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			if ( function_exists( 'spokora_render_pro_teaser' ) ) {
				spokora_render_pro_teaser( 'compact' );
			}
			?>
			<p>
				<?php echo esc_html__( 'If a Rank Math Pro site ran a tainted 3.0.123 package, restore from a clean backup before you migrate.', 'spokora-seo-toolkit' ); ?>
			</p>
		</div>
		<?php
	}
}

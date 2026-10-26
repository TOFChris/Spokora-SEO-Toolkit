<?php
/**
 * Spokora SEO Toolkit → Modules.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

/**
 * Enable/disable Free modules only.
 */
class Spokora_Modules_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 25 );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function add_submenu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Spokora Modules', 'spokora-seo-toolkit' ),
			__( 'Modules', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-modules',
			array( $this, 'render_page' )
		);
	}

	public function register() {
		register_setting(
			'spokora_modules_group',
			'spokora_modules',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'spokora_sanitize_modules_option',
				'default'           => spokora_get_default_modules(),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$manifest = spokora_get_module_manifest();
		$values   = spokora_get_merged_modules();
		$forced   = apply_filters( 'spokora_forced_modules', array( 'settings' ) );
		$blurbs   = spokora_get_module_help_blurbs();

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Module settings saved.', 'spokora-seo-toolkit' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<?php spokora_render_subnav( 'modules' ); ?>
			<h1><?php echo esc_html__( 'Spokora SEO Toolkit — Modules', 'spokora-seo-toolkit' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'These are the SEO tools in this plugin. Settings stays on so import and site defaults remain available.', 'spokora-seo-toolkit' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'spokora_modules_group' ); ?>
				<table class="widefat striped" style="max-width: 720px;">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Enable', 'spokora-seo-toolkit' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Module', 'spokora-seo-toolkit' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Summary', 'spokora-seo-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $manifest as $id => $meta ) : ?>
							<?php
							$is_forced = in_array( $id, $forced, true );
							$checked   = ! empty( $values[ $id ] );
							$summary   = isset( $blurbs[ $id ]['summary'] ) ? $blurbs[ $id ]['summary'] : '';
							?>
							<tr>
								<td>
									<?php if ( $is_forced ) : ?>
										<input type="checkbox" checked disabled />
										<input type="hidden" name="spokora_modules[<?php echo esc_attr( $id ); ?>]" value="1" />
									<?php else : ?>
										<input type="hidden" name="spokora_modules[<?php echo esc_attr( $id ); ?>]" value="0" />
										<input type="checkbox" name="spokora_modules[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $checked, true ); ?> />
									<?php endif; ?>
								</td>
								<td><strong><?php echo esc_html( $meta['label'] ); ?></strong><br /><code><?php echo esc_html( $id ); ?></code></td>
								<td><?php echo esc_html( $summary ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save modules', 'spokora-seo-toolkit' ) ); ?>
			</form>
			<?php if ( function_exists( 'spokora_pro_locked_modules' ) ) : ?>
				<h2><?php echo esc_html__( 'In Spokora Pro', 'spokora-seo-toolkit' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'These stay off in this plugin on purpose. Checking them here would not load them — they are a preview of the paid plugin.', 'spokora-seo-toolkit' ); ?></p>
				<table class="widefat striped spokora-pro-locked" style="max-width: 720px;">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Enable', 'spokora-seo-toolkit' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Module', 'spokora-seo-toolkit' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Summary', 'spokora-seo-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( spokora_pro_locked_modules() as $id => $meta ) : ?>
							<tr>
								<td>
									<input type="checkbox" disabled aria-disabled="true" />
									<span class="spokora-pro-badge"><?php echo esc_html__( 'Pro', 'spokora-seo-toolkit' ); ?></span>
								</td>
								<td><strong><?php echo esc_html( $meta['label'] ); ?></strong><br /><code><?php echo esc_html( $id ); ?></code></td>
								<td><?php echo esc_html( $meta['summary'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><?php echo function_exists( 'spokora_pro_cta_html' ) ? wp_kses_post( spokora_pro_cta_html() ) : ''; ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

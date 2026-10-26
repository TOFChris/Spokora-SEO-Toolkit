<?php
/**
 * Module: Search ping — IndexNow when a public page changes.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Spokora_IndexNow {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 22 );
		add_action( 'template_redirect', 'spokora_indexnow_maybe_serve', 0 );
		add_action( 'transition_post_status', 'spokora_indexnow_on_status', 10, 3 );
		add_action( 'admin_post_spokora_indexnow_ping', array( $this, 'manual_ping' ) );
		add_action( 'admin_post_spokora_indexnow_toggle', array( $this, 'save_toggle' ) );
	}

	public function admin_menu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Search ping', 'spokora-seo-toolkit' ),
			__( 'Search ping', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-indexnow',
			array( $this, 'render_page' )
		);
	}

	public function save_toggle() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'spokora_indexnow_toggle' ) ) {
			wp_die( esc_html__( 'Please try that again.', 'spokora-seo-toolkit' ) );
		}
		$raw = isset( $_POST['spokora_indexnow_enabled'] ) ? wp_unslash( $_POST['spokora_indexnow_enabled'] ) : '0';
		if ( is_array( $raw ) ) {
			$raw = end( $raw );
		}
		$on = '1' === (string) $raw;
		update_option( 'spokora_indexnow_enabled', $on ? '1' : '0', false );
		wp_safe_redirect( admin_url( 'admin.php?page=spokora-indexnow&saved=1' ) );
		exit;
	}

	public function manual_ping() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'spokora_indexnow_ping' ) ) {
			wp_die( esc_html__( 'Please try that again.', 'spokora-seo-toolkit' ) );
		}
		if ( function_exists( 'spokora_indexnow_is_enabled' ) && ! spokora_indexnow_is_enabled() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=spokora-indexnow&need_enable=1' ) );
			exit;
		}
		spokora_indexnow_ping( home_url( '/' ), true );
		wp_safe_redirect( admin_url( 'admin.php?page=spokora-indexnow&pinged=1' ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last    = get_option( 'spokora_indexnow_last', array() );
		$enabled = function_exists( 'spokora_indexnow_is_enabled' ) ? spokora_indexnow_is_enabled() : false;
		$key     = $enabled ? spokora_indexnow_key_url() : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Search ping', 'spokora-seo-toolkit' ); ?></h1>
			<p><?php echo esc_html__( 'When enabled, Spokora tells IndexNow (api.indexnow.org) when a public page is published, updated, or taken down. Bing and other participating engines use that. Google does not. Off by default so the plugin does not call an external service until you choose it.', 'spokora-seo-toolkit' ); ?></p>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Search ping setting saved.', 'spokora-seo-toolkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['need_enable'] ) ) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'Turn Search ping on before sending a test.', 'spokora-seo-toolkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['pinged'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'The homepage was sent.', 'spokora-seo-toolkit' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 1em 0 1.5em;">
				<input type="hidden" name="action" value="spokora_indexnow_toggle" />
				<?php wp_nonce_field( 'spokora_indexnow_toggle' ); ?>
				<input type="hidden" name="spokora_indexnow_enabled" value="0" />
				<label>
					<input type="checkbox" name="spokora_indexnow_enabled" value="1" <?php checked( $enabled ); ?> />
					<?php echo esc_html__( 'Ping IndexNow when public pages change', 'spokora-seo-toolkit' ); ?>
				</label>
				<?php submit_button( __( 'Save', 'spokora-seo-toolkit' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( $enabled && $key ) : ?>
			<p><strong><?php echo esc_html__( 'Key file', 'spokora-seo-toolkit' ); ?></strong><br /><a href="<?php echo esc_url( $key ); ?>"><code><?php echo esc_html( $key ); ?></code></a></p>
			<?php endif; ?>
			<?php if ( is_array( $last ) && ! empty( $last['url'] ) ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: time, 2: URL, 3: HTTP code or "sent" */
							__( 'Last ping %1$s — %2$s (%3$s)', 'spokora-seo-toolkit' ),
							wp_date( 'M j, Y g:i a', isset( $last['time'] ) ? (int) $last['time'] : time() ),
							(string) $last['url'],
							! empty( $last['code'] ) ? (string) (int) $last['code'] : __( 'sent', 'spokora-seo-toolkit' )
						)
					);
					?>
				</p>
			<?php else : ?>
				<p><?php echo esc_html__( 'No ping yet. The next publish will send one.', 'spokora-seo-toolkit' ); ?></p>
			<?php endif; ?>
			<?php if ( $enabled ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="spokora_indexnow_ping" />
				<?php wp_nonce_field( 'spokora_indexnow_ping' ); ?>
				<?php submit_button( __( 'Send the homepage now', 'spokora-seo-toolkit' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}
}

new Spokora_IndexNow();

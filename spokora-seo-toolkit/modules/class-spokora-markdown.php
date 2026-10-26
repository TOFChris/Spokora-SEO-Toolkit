<?php
/**
 * Module: Markdown — a copy of every published post and page.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Spokora_Markdown {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 22 );
	}

	public function admin_menu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Markdown library', 'spokora-seo-toolkit' ),
			__( 'Markdown library', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-markdown',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$types = function_exists( 'spokora_scoring_post_types' ) ? spokora_scoring_post_types() : array( 'post', 'page' );
		$posts = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<?php spokora_render_subnav( 'markdown' ); ?>
			<h1><?php echo esc_html__( 'Markdown library', 'spokora-seo-toolkit' ); ?></h1>
			<p><?php echo esc_html__( 'Read-only index of automatic markdown mirrors. Every published URL listed here also serves /md/{slug}.md with title, link, and body text. These mirrors are not the AI hub (/llms.txt) and not the optional spokes (/ai-docs/). Configure those under Create → AI docs.', 'spokora-seo-toolkit' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=spokora-llms' ) ); ?>"><?php echo esc_html__( 'Open AI docs (hub + spokes)', 'spokora-seo-toolkit' ); ?></a></p>
			<?php if ( ! $posts ) : ?>
				<p><?php echo esc_html__( 'No published posts or pages yet.', 'spokora-seo-toolkit' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:920px;">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Title', 'spokora-seo-toolkit' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Markdown', 'spokora-seo-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $posts as $post ) : ?>
							<?php
							if ( ! $post instanceof WP_Post || '' === $post->post_name || post_password_required( $post ) ) {
								continue;
							}
							$url = home_url( '/md/' . rawurlencode( $post->post_name ) . '.md' );
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
								</td>
								<td><a href="<?php echo esc_url( $url ); ?>"><code><?php echo esc_html( wp_parse_url( $url, PHP_URL_PATH ) ); ?></code></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( 100 === count( $posts ) ) : ?>
					<p><?php echo esc_html__( 'Showing the 100 most recently updated. Every other published page still has a markdown copy at the same kind of address.', 'spokora-seo-toolkit' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

new Spokora_Markdown();

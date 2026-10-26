<?php
/**
 * Dismissible welcome blurb on Spokora SEO Toolkit → Home.
 *
 * Copy source: docs/HOME-DASHBOARD-BLURB.md (this repo)
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * @return array{paragraphs: list<string>, signoff: string, tabs: list<array{label: string, url: string, external?: bool}>}
 */
function spokora_get_home_intro_copy() {
	$copy = array(
		'paragraphs' => array(
			__( 'This one is on the house. Titles, schema, sitemap, redirects — the SEO stack, no meter, no account. You do not register this site and you do not need a key. Updates come through WordPress like any other directory plugin. Optional: sign up so we can email you when a build ships.', 'spokora-seo-toolkit' ),
			__( 'If it saves you a headache, the kindest thank-you is a Pro license when you need the rest of a client week. If you like it, leave us a review. If you just want to buy the next pot of coffee, that works too. Old school. We still notice.', 'spokora-seo-toolkit' ),
		),
		'signoff'    => __( '— Chris, That One Firm', 'spokora-seo-toolkit' ),
		'tabs'       => array(
			array(
				'label' => __( 'Sign up for updates', 'spokora-seo-toolkit' ),
				'url'   => spokora_updates_signup_url(),
			),
			array(
				'label' => __( 'Support', 'spokora-seo-toolkit' ),
				'url'   => function_exists( 'spokora_wporg_support_url' ) ? spokora_wporg_support_url() : 'https://wordpress.org/support/plugin/spokora-seo-toolkit/',
				'external' => true,
			),
			array(
				'label' => __( 'Updates', 'spokora-seo-toolkit' ),
				'url'   => spokora_updates_page_url(),
			),
			array(
				'label' => __( 'Leave a review', 'spokora-seo-toolkit' ),
				'url'   => spokora_free_review_url(),
			),
		),
	);
	return apply_filters( 'spokora_home_intro_copy', $copy );
}

/**
 * @return bool
 */
function spokora_home_intro_is_visible() {
	$until = (int) get_user_meta( get_current_user_id(), 'spokora_home_note_hidden_until', true );
	return $until <= time();
}

/**
 * Render the Home intro box (or nothing when snoozed).
 */
function spokora_render_home_intro() {
	if ( ! spokora_home_intro_is_visible() ) {
		return;
	}
	$copy = spokora_get_home_intro_copy();
	if ( empty( $copy['paragraphs'] ) ) {
		return;
	}
	?>
	<div class="spokora-home-coffee">
		<form class="spokora-home-coffee__dismiss" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="spokora_snooze_home_note" />
			<?php wp_nonce_field( 'spokora_snooze_home_note' ); ?>
			<button type="submit" class="spokora-home-coffee__hide">
				<span class="screen-reader-text"><?php echo esc_html__( 'Hide this note for a month', 'spokora-seo-toolkit' ); ?></span>
				<span aria-hidden="true">&times;</span>
			</button>
		</form>
		<?php foreach ( $copy['paragraphs'] as $paragraph ) : ?>
			<p><?php echo esc_html( $paragraph ); ?></p>
		<?php endforeach; ?>
		<?php if ( ! empty( $copy['signoff'] ) ) : ?>
			<p class="spokora-home-coffee__thanks"><?php echo esc_html( (string) $copy['signoff'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $copy['tabs'] ) ) : ?>
			<nav class="spokora-home-coffee__tabs" aria-label="<?php echo esc_attr__( 'Stay in touch', 'spokora-seo-toolkit' ); ?>">
				<?php foreach ( $copy['tabs'] as $tab ) : ?>
					<a
						class="spokora-home-coffee__tab"
						href="<?php echo esc_url( (string) $tab['url'] ); ?>"
						<?php echo ! empty( $tab['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
					><?php echo esc_html( (string) $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
}

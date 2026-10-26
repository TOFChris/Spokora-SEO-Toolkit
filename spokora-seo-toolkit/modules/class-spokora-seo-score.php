<?php
/**
 * SEO + AI readiness scores — metabox UI, list columns, block sidebar, site overview.
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Access denied.' );
}

class Spokora_SEO_Score_Engine {

	public function __construct() {
		add_action( 'spokora_render_seo_score_panel', array( $this, 'render_score_panel' ), 10, 1 );
		add_action( 'spokora_after_seo_fields', array( $this, 'render_serp_and_counts' ), 10, 1 );
		add_action( 'save_post', array( $this, 'save_exclude_and_scores' ), 20, 1 );
		add_action( 'save_post', array( $this, 'finalize_scores_after_save' ), 25, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_spokora_recalc_seo_scores', array( $this, 'ajax_recalc' ) );
		add_action( 'wp_ajax_spokora_explain_seo_score', array( $this, 'ajax_explain' ) );

		foreach ( spokora_scoring_post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'add_list_columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_list_column' ), 10, 2 );
		}

		add_action( 'admin_menu', array( $this, 'register_overview_page' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_score_panel( $post ) {
		$scores   = spokora_get_page_scores( $post->ID );
		$excluded = get_post_meta( $post->ID, '_spokora_seo_score_exclude', true ) === '1';
		$search   = $scores['search'];
		$ai       = $scores['ai'];

		$panel_class = 'spokora-seo-score-panel ' . esc_attr( $this->status_css_class( $search['status'] ) );
		?>
		<div id="spokora-seo-score-panel" class="<?php echo esc_attr( $panel_class ); ?>">
			<div class="spokora-seo-score-gauges">
				<div class="spokora-seo-gauge spokora-seo-gauge--search <?php echo esc_attr( $this->status_css_class( $search['status'] ) ); ?>">
					<div class="spokora-seo-gauge__ring" style="--spokora-score: <?php echo (int) $search['score']; ?>;">
						<span class="spokora-seo-gauge__value"><?php echo (int) $search['score']; ?></span>
					</div>
					<div>
						<div class="spokora-seo-gauge__label"><?php esc_html_e( 'Search SEO', 'spokora-seo-toolkit' ); ?></div>
						<div class="spokora-seo-gauge__sublabel"><?php esc_html_e( 'Classic on-page', 'spokora-seo-toolkit' ); ?></div>
					</div>
				</div>
				<div class="spokora-seo-gauge spokora-seo-gauge--ai <?php echo esc_attr( $this->status_css_class( $ai['status'] ) ); ?>">
					<div class="spokora-seo-gauge__ring" style="--spokora-score: <?php echo (int) $ai['score']; ?>;">
						<span class="spokora-seo-gauge__value"><?php echo (int) $ai['score']; ?></span>
					</div>
					<div>
						<div class="spokora-seo-gauge__label"><?php esc_html_e( 'AI readiness', 'spokora-seo-toolkit' ); ?></div>
						<div class="spokora-seo-gauge__sublabel"><?php esc_html_e( 'LLMS / ai-docs', 'spokora-seo-toolkit' ); ?></div>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $search['content_source']['label'] ) ) : ?>
				<span class="spokora-seo-source-badge">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: content source label */
							__( 'Scoring content from: %s', 'spokora-seo-toolkit' ),
							$search['content_source']['label']
						)
					);
					?>
				</span>
			<?php endif; ?>

			<p style="margin:0 0 8px;">
				<label>
					<input type="checkbox" name="spokora_seo_score_exclude" value="1" <?php checked( $excluded ); ?> />
					<?php esc_html_e( 'Exclude from SEO scoring (contact/legal pages)', 'spokora-seo-toolkit' ); ?>
				</label>
			</p>

			<details class="spokora-fold" open style="margin-bottom:8px;">
				<summary><strong><?php esc_html_e( 'Search SEO checks', 'spokora-seo-toolkit' ); ?></strong></summary>
				<ul class="spokora-seo-checklist spokora-seo-checklist--search">
					<?php $this->render_checklist_items( $search['checks'] ); ?>
				</ul>
			</details>

			<details class="spokora-fold" style="margin-bottom:8px;">
				<summary><strong><?php esc_html_e( 'AI readiness checks', 'spokora-seo-toolkit' ); ?></strong></summary>
				<ul class="spokora-seo-checklist spokora-seo-checklist--ai">
					<?php $this->render_checklist_items( $ai['checks'] ); ?>
				</ul>
			</details>

			<p style="margin:8px 0 0;">
				<button type="button" class="button button-secondary" id="spokora-seo-explain-btn">
					<?php esc_html_e( 'Explain scores', 'spokora-seo-toolkit' ); ?>
				</button>
				<span id="spokora-seo-explain-result" class="description" style="display:block;margin-top:8px;"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * @param array<int, array<string, mixed>> $checks Checks.
	 */
	private function render_checklist_items( array $checks ) {
		foreach ( $checks as $check ) {
			$icon = 'marker';
			if ( 'pass' === $check['status'] ) {
				$icon = 'yes-alt';
			} elseif ( 'warn' === $check['status'] ) {
				$icon = 'warning';
			} elseif ( 'fail' === $check['status'] ) {
				$icon = 'dismiss';
			}
			echo '<li>';
			echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . ' status-' . esc_attr( $check['status'] ) . '"></span>';
			echo '<span><strong>' . esc_html( $check['label'] ) . '</strong> — ' . esc_html( $check['message'] ) . '</span>';
			echo '</li>';
		}
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_serp_and_counts( $post ) {
		$scores = spokora_get_page_scores( $post->ID );
		$serp   = $scores['search']['serp'] ?? array();
		?>
		<div class="spokora-serp-preview" aria-label="<?php esc_attr_e( 'Search result preview', 'spokora-seo-toolkit' ); ?>">
			<p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Google preview (approximate)', 'spokora-seo-toolkit' ); ?></p>
			<p class="spokora-serp-preview__title"><?php echo esc_html( $serp['title'] ?? '' ); ?></p>
			<p class="spokora-serp-preview__url"><?php echo esc_url( $serp['url'] ?? home_url( '/' ) ); ?></p>
			<p class="spokora-serp-preview__desc"><?php echo esc_html( $serp['description'] ?? '' ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param string $status good|ok|poor|na.
	 */
	private function status_css_class( $status ) {
		if ( 'good' === $status ) {
			return 'is-good';
		}
		if ( 'ok' === $status ) {
			return 'is-ok';
		}
		if ( 'na' === $status ) {
			return 'is-na';
		}
		return 'is-poor';
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function save_exclude_and_scores( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! in_array( get_post_type( $post_id ), spokora_scoring_post_types(), true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['spokora_seo_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spokora_seo_meta_nonce'] ) ), 'spokora_seo_save_meta' ) ) {
			$exclude = ! empty( $_POST['spokora_seo_score_exclude'] ) ? '1' : '0';
			update_post_meta( $post_id, '_spokora_seo_score_exclude', $exclude );
		}

		$overrides = array();
		if ( isset( $_POST['spokora_seo_title'] ) ) {
			$overrides['title'] = sanitize_text_field( wp_unslash( $_POST['spokora_seo_title'] ) );
		}
		if ( isset( $_POST['spokora_seo_desc'] ) ) {
			$overrides['description'] = sanitize_textarea_field( wp_unslash( $_POST['spokora_seo_desc'] ) );
		}
		if ( function_exists( 'spokora_scoring_llms_meta_submitted' ) && spokora_scoring_llms_meta_submitted() ) {
			if ( isset( $_POST['spokora_llms_summary'] ) ) {
				$overrides['llms_summary'] = sanitize_text_field( wp_unslash( $_POST['spokora_llms_summary'] ) );
			}
			if ( isset( $_POST['spokora_llms_md'] ) ) {
				$overrides['llms_md'] = sanitize_textarea_field( wp_unslash( $_POST['spokora_llms_md'] ) );
			}
			$overrides['llms_public'] = ! empty( $_POST['spokora_llms_public'] ) ? '1' : '';
		}

		spokora_compute_and_save_scores( $post_id, $overrides );
	}

	/**
	 * After all metaboxes persist meta, recompute from the database (fixes block editor / REST saves).
	 *
	 * @param int $post_id Post ID.
	 */
	public function finalize_scores_after_save( $post_id ) {
		if ( function_exists( 'spokora_finalize_post_scores' ) ) {
			spokora_finalize_post_scores( $post_id );
		}
	}

	/**
	 * @param string $hook Admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, spokora_scoring_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'spokora-seo-score-admin',
			SPOKORA_PLUGIN_URL . 'assets/css/seo-score-admin.css',
			array(),
			SPOKORA_VERSION
		);
		wp_enqueue_script(
			'spokora-seo-score-admin',
			SPOKORA_PLUGIN_URL . 'assets/js/seo-score-admin.js',
			array( 'jquery' ),
			SPOKORA_VERSION,
			true
		);

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_localize_script(
			'spokora-seo-score-admin',
			'spokoraSeoScore',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'spokora_seo_score_nonce' ),
				'postId'  => $post_id,
				'i18n'    => array(
					'scoringFrom' => __( 'Scoring content from:', 'spokora-seo-toolkit' ),
				),
			)
		);

		wp_add_inline_script(
			'spokora-seo-score-admin',
			"jQuery(function($){ $('#spokora-seo-explain-btn').on('click', function(){
				var btn=$(this), out=$('#spokora-seo-explain-result');
				btn.prop('disabled',true); out.text('" . esc_js( __( 'Asking the model…', 'spokora-seo-toolkit' ) ) . "');
				$.post(spokoraSeoScore.ajaxUrl, {
					action:'spokora_explain_seo_score', nonce:spokoraSeoScore.nonce, post_id:spokoraSeoScore.postId
				}).done(function(r){ out.text(r.success ? r.data : r.data); })
				.always(function(){ btn.prop('disabled',false); });
			}); });",
			'after'
		);
	}

	public function ajax_recalc() {
		check_ajax_referer( 'spokora_seo_score_nonce', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'spokora-seo-toolkit' ) );
		}

		$overrides = isset( $_POST['overrides'] ) && is_array( $_POST['overrides'] )
			? wp_unslash( $_POST['overrides'] )
			: array();

		if ( ! empty( $overrides['excluded'] ) ) {
			update_post_meta( $post_id, '_spokora_seo_score_exclude', '1' );
		} else {
			update_post_meta( $post_id, '_spokora_seo_score_exclude', '0' );
		}

		$scores = spokora_get_page_scores( $post_id, $overrides );
		wp_send_json_success( $scores );
	}

	public function ajax_explain() {
		check_ajax_referer( 'spokora_seo_score_nonce', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'spokora-seo-toolkit' ) );
		}

		$scores = spokora_get_page_scores( $post_id );
		$lines  = array( 'Search SEO score: ' . $scores['search']['score'] . '/100', 'AI readiness: ' . $scores['ai']['score'] . '/100', 'Failed or weak checks:' );
		foreach ( array( 'search', 'ai' ) as $key ) {
			foreach ( $scores[ $key ]['checks'] as $check ) {
				if ( in_array( $check['status'], array( 'fail', 'warn' ), true ) ) {
					$lines[] = '- ' . $check['label'] . ': ' . $check['message'];
				}
			}
		}

		$prompt = "You are an SEO coach for WordPress editors. Given these checklist results, give 3-5 short, actionable bullet points to improve the page. Be specific and concise.\n\n" . implode( "\n", $lines );

		if ( ! function_exists( 'spokora_model_complete' ) ) {
			wp_send_json_error( __( 'Model client is not loaded.', 'spokora-seo-toolkit' ) );
		}

		$text = spokora_model_complete( $prompt, 45 );
		if ( is_wp_error( $text ) ) {
			wp_send_json_error( $text->get_error_message() );
		}
		if ( trim( (string) $text ) === '' ) {
			wp_send_json_error( __( 'The model could not explain scores.', 'spokora-seo-toolkit' ) );
		}

		wp_send_json_success( trim( (string) $text ) );
	}

	/**
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function add_list_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['spokora_seo_score'] = __( 'SEO', 'spokora-seo-toolkit' );
				$new['spokora_ai_score']  = __( 'AI', 'spokora-seo-toolkit' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_list_column( $column, $post_id ) {
		if ( 'spokora_seo_score' === $column ) {
			$this->render_score_pill( (int) get_post_meta( $post_id, '_spokora_seo_score', true ), spokora_score_status_from_number( (int) get_post_meta( $post_id, '_spokora_seo_score', true ) ), $post_id );
		}
		if ( 'spokora_ai_score' === $column ) {
			$this->render_score_pill( (int) get_post_meta( $post_id, '_spokora_llms_score', true ), spokora_score_status_from_number( (int) get_post_meta( $post_id, '_spokora_llms_score', true ) ), $post_id );
		}
	}

	/**
	 * @param int    $score Score.
	 * @param string $status Status class suffix.
	 */
	private function render_score_pill( $score, $status, $post_id = 0 ) {
		$updated = $post_id > 0 ? get_post_meta( $post_id, '_spokora_seo_score_updated', true ) : '';
		if ( $score <= 0 && $updated === '' ) {
			echo '<span class="spokora-score-pill is-na">—</span>';
			return;
		}
		echo '<span class="spokora-score-pill ' . esc_attr( $this->status_css_class( $status ) ) . '">' . (int) $score . '</span>';
	}

	public function register_overview_page() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'Page scores', 'spokora-seo-toolkit' ),
			__( 'Page scores', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-seo-scores',
			array( $this, 'render_overview_page' )
		);
	}

	public function render_overview_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => spokora_scoring_post_types(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Page scores', 'spokora-seo-toolkit' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Site-wide view of saved Search SEO and AI readiness scores. Edit a page to refresh its checklist.', 'spokora-seo-toolkit' ); ?></p>
			<?php
			if ( function_exists( 'spokora_render_pro_teaser' ) ) {
				spokora_render_pro_teaser( 'keys' );
			}
			?>
			<table class="widefat striped" style="margin-top:16px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'spokora-seo-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Type', 'spokora-seo-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Search SEO', 'spokora-seo-toolkit' ); ?></th>
						<th><?php esc_html_e( 'AI readiness', 'spokora-seo-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'spokora-seo-toolkit' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $posts ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No scored content yet.', 'spokora-seo-toolkit' ); ?></td></tr>
				<?php else : ?>
					<?php
					foreach ( $posts as $p ) {
						$seo = (int) get_post_meta( $p->ID, '_spokora_seo_score', true );
						$ai  = (int) get_post_meta( $p->ID, '_spokora_llms_score', true );
						$ts  = (int) get_post_meta( $p->ID, '_spokora_seo_score_updated', true );
						if ( $ts === 0 && $seo === 0 && $ai === 0 ) {
							$computed = spokora_compute_and_save_scores( $p->ID );
							$seo      = (int) $computed['search']['score'];
							$ai       = (int) $computed['ai']['score'];
							$ts       = time();
						}
						?>
						<tr>
							<td><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong></td>
							<td><?php echo esc_html( $p->post_type ); ?></td>
							<td><?php $this->render_score_pill( $seo, spokora_score_status_from_number( $seo ) ); ?></td>
							<td><?php $this->render_score_pill( $ai, spokora_score_status_from_number( $ai ) ); ?></td>
							<td><?php echo $ts ? esc_html( gmdate( 'Y-m-d H:i', $ts ) ) : '—'; ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php esc_html_e( 'Edit', 'spokora-seo-toolkit' ); ?></a></td>
						</tr>
						<?php
					}
					?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function enqueue_block_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, spokora_scoring_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'spokora-seo-score-sidebar',
			SPOKORA_PLUGIN_URL . 'assets/js/seo-score-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'jquery' ),
			SPOKORA_VERSION,
			true
		);

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scores  = function_exists( 'spokora_get_page_scores_sidebar_boot' )
			? spokora_get_page_scores_sidebar_boot( $post_id )
			: array(
				'search' => array( 'score' => 0, 'status' => 'na' ),
				'ai'     => array( 'score' => 0, 'status' => 'na' ),
			);

		wp_localize_script(
			'spokora-seo-score-sidebar',
			'spokoraSeoScoreSidebar',
			array(
				'postId'  => $post_id,
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'spokora_seo_score_nonce' ),
				'initial' => $scores,
			)
		);
	}
}

new Spokora_SEO_Score_Engine();

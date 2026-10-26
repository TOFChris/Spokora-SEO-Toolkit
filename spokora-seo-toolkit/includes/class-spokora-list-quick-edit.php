<?php
/**
 * Native WordPress list-table Quick Edit for Spokora SEO + AI fields.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spokora_List_Quick_Edit {

	public function __construct() {
		$types = $this->post_types();
		foreach ( $types as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'ensure_column' ), 25 );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_inline_payload' ), 20, 2 );
		}
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @return string[]
	 */
	private function post_types() {
		return function_exists( 'spokora_scoring_post_types' )
			? spokora_scoring_post_types()
			: array( 'post', 'page' );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function ensure_column( $columns ) {
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}
		if ( isset( $columns['spokora_seo_score'] ) ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['spokora_seo_score'] = __( 'SEO', 'spokora-seo-toolkit' );
			}
		}
		if ( ! isset( $out['spokora_seo_score'] ) ) {
			$out['spokora_seo_score'] = __( 'SEO', 'spokora-seo-toolkit' );
		}
		return $out;
	}

	/**
	 * Hidden JSON WordPress Quick Edit reads when the row opens.
	 *
	 * @param string $column  Column id.
	 * @param int    $post_id Post ID.
	 */
	public function render_inline_payload( $column, $post_id ) {
		if ( 'spokora_seo_score' !== $column ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$kws = function_exists( 'spokora_get_post_keywords' )
			? spokora_get_post_keywords( $post_id )
			: array( 'primary' => '', 'additional' => array() );
		$payload = array(
			'title'      => (string) get_post_meta( $post_id, '_spokora_seo_title', true ),
			'desc'       => (string) get_post_meta( $post_id, '_spokora_seo_desc', true ),
			'keyword'    => isset( $kws['primary'] ) ? (string) $kws['primary'] : '',
			'keywords'   => isset( $kws['additional'] ) && is_array( $kws['additional'] ) ? implode( "\n", $kws['additional'] ) : '',
			'kind'       => (string) get_post_meta( $post_id, '_spokora_content_kind', true ),
			'exclude'    => get_post_meta( $post_id, '_spokora_seo_score_exclude', true ) === '1' ? '1' : '0',
			'public'     => get_post_meta( $post_id, '_spokora_llms_public', true ) === '1' ? '1' : '0',
			'include'    => get_post_meta( $post_id, '_spokora_llms_include', true ) === '1' ? '1' : '0',
			'summary'    => (string) get_post_meta( $post_id, '_spokora_llms_summary', true ),
			'md'         => (string) get_post_meta( $post_id, '_spokora_llms_md', true ),
		);
		echo '<textarea class="spokora-inline-json hidden" hidden readonly cols="1" rows="1">';
		echo esc_textarea( wp_json_encode( $payload ) );
		echo '</textarea>';
	}

	/**
	 * Fields inside the core Quick Edit row (Posts / Pages).
	 *
	 * @param string $column    Column id.
	 * @param string $post_type Post type.
	 */
	public function render_quick_edit( $column, $post_type ) {
		if ( 'spokora_seo_score' !== $column ) {
			return;
		}
		if ( ! in_array( $post_type, $this->post_types(), true ) ) {
			return;
		}
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		wp_nonce_field( 'spokora_seo_save_meta', 'spokora_seo_meta_nonce' );
		wp_nonce_field( 'spokora_llms_save_meta', 'spokora_llms_meta_nonce' );
		?>
		<fieldset class="inline-edit-col-left spokora-inline-edit" style="width:100%;">
			<legend class="inline-edit-legend"><?php echo esc_html__( 'Spokora — SEO & AI', 'spokora-seo-toolkit' ); ?></legend>
			<div class="inline-edit-col spokora-inline-edit__grid">
				<label>
					<span class="title"><?php echo esc_html__( 'SEO title', 'spokora-seo-toolkit' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="spokora_seo_title" value="" /></span>
				</label>
				<label>
					<span class="title"><?php echo esc_html__( 'Meta description', 'spokora-seo-toolkit' ); ?></span>
					<span class="input-text-wrap"><textarea name="spokora_seo_desc" rows="2"></textarea></span>
				</label>
				<label>
					<span class="title"><?php echo esc_html__( 'Focus keyphrase', 'spokora-seo-toolkit' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="spokora_focus_keyword" value="" /></span>
				</label>
				<label>
					<span class="title"><?php echo esc_html__( 'More keyphrases', 'spokora-seo-toolkit' ); ?></span>
					<span class="input-text-wrap"><textarea name="spokora_additional_keywords" rows="2" placeholder="<?php echo esc_attr__( 'One per line', 'spokora-seo-toolkit' ); ?>"></textarea></span>
				</label>
				<label>
					<span class="title"><?php echo esc_html__( 'What is this?', 'spokora-seo-toolkit' ); ?></span>
					<select name="spokora_content_kind">
						<option value=""><?php echo esc_html__( 'Ordinary page or post', 'spokora-seo-toolkit' ); ?></option>
						<option value="article"><?php echo esc_html__( 'Article', 'spokora-seo-toolkit' ); ?></option>
						<option value="news"><?php echo esc_html__( 'News article', 'spokora-seo-toolkit' ); ?></option>
						<option value="video"><?php echo esc_html__( 'Video', 'spokora-seo-toolkit' ); ?></option>
					</select>
				</label>
				<label class="alignleft">
					<input type="checkbox" name="spokora_seo_score_exclude" value="1" />
					<span class="checkbox-title"><?php echo esc_html__( 'Exclude from scoring', 'spokora-seo-toolkit' ); ?></span>
				</label>
				<br class="clear" />
				<label class="alignleft">
					<input type="checkbox" name="spokora_llms_public" value="1" />
					<span class="checkbox-title"><?php echo esc_html__( 'Public AI document', 'spokora-seo-toolkit' ); ?></span>
				</label>
				<label class="alignleft">
					<input type="checkbox" name="spokora_llms_include" value="1" />
					<span class="checkbox-title"><?php echo esc_html__( 'Include in llms hub', 'spokora-seo-toolkit' ); ?></span>
				</label>
				<br class="clear" />
				<label>
					<span class="title"><?php echo esc_html__( 'AI summary', 'spokora-seo-toolkit' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="spokora_llms_summary" value="" /></span>
				</label>
				<label>
					<span class="title"><?php echo esc_html__( 'AI markdown', 'spokora-seo-toolkit' ); ?></span>
					<span class="input-text-wrap"><textarea name="spokora_llms_md" rows="4"></textarea></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * @param string $hook Admin hook.
	 */
	public function enqueue( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, $this->post_types(), true ) ) {
			return;
		}
		wp_enqueue_script(
			'spokora-list-quick-edit',
			SPOKORA_PLUGIN_URL . 'assets/js/list-quick-edit.js',
			array( 'jquery', 'inline-edit-post' ),
			SPOKORA_VERSION,
			true
		);
	}
}

new Spokora_List_Quick_Edit();

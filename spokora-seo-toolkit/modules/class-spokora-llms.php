<?php
/**
 * LLM / AI documentation — hub (/llms.txt) and spokes (/ai-docs/{slug}.md).
 *
 * @package Spokora
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Access denied.' );
}

/**
 * Registers rewrite tags/rules (callable before modules load on activation flush).
 */
function spokora_llms_register_rewrite_rules() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?spokora_llms_hub=1', 'top' );
	add_rewrite_rule( '^ai-docs/([^/]+)\.md$', 'index.php?spokora_ai_doc_slug=$matches[1]', 'top' );
}

/**
 * Post types that support AI docs.
 *
 * @return string[]
 */
function spokora_llms_supported_post_types() {
	$types = function_exists( 'spokora_editor_metabox_post_types' )
		? spokora_editor_metabox_post_types()
		: array( 'post', 'page', 'spokora_show', 'spokora_episode' );
	return apply_filters( 'spokora_llms_post_types', $types );
}

class Spokora_Llms_Engine {

	public function __construct() {
		add_action( 'init', array( $this, 'boot_rewrites' ), 5 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_llms' ), 0 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post_meta' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * @param string $hook Admin hook.
	 */
	public function enqueue_editor_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( ! function_exists( 'spokora_is_module_enabled' ) || ! spokora_is_module_enabled( 'llms' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, spokora_llms_supported_post_types(), true ) ) {
			return;
		}
		if ( ! function_exists( 'spokora_model_is_configured' ) || ! spokora_model_is_configured() ) {
			return;
		}

		wp_enqueue_script(
			'spokora-editor-llms-ai',
			SPOKORA_PLUGIN_URL . 'assets/js/editor-llms-ai.js',
			array( 'jquery' ),
			defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '1.0.0',
			true
		);
		wp_localize_script(
			'spokora-editor-llms-ai',
			'spokoraEditorLlmsAi',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'spokora_ai_nonce' ),
				'i18n'    => array(
					'saveDraftFirst' => __( 'Save the post as a draft first.', 'spokora-seo-toolkit' ),
					'working'        => __( 'Asking the model…', 'spokora-seo-toolkit' ),
					'done'           => __( 'Draft ready. Review the summary and markdown, then Update or Publish.', 'spokora-seo-toolkit' ),
					'doneFrom'       => __( 'Draft ready (content from:', 'spokora-seo-toolkit' ),
					'noContent'      => __( 'No content source found. Add editor text or save a draft.', 'spokora-seo-toolkit' ),
					'failed'         => __( 'Server communication failed.', 'spokora-seo-toolkit' ),
				),
			)
		);
	}

	public function boot_rewrites() {
		spokora_llms_register_rewrite_rules();

		$stored = get_option( 'spokora_llms_rewrite_version', '' );
		if ( (string) $stored !== (string) SPOKORA_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'spokora_llms_rewrite_version', SPOKORA_VERSION );
		}
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = 'spokora_llms_hub';
		$vars[] = 'spokora_ai_doc_slug';
		return $vars;
	}

	public function maybe_serve_llms() {
		if ( is_admin() ) {
			return;
		}

		$hub = get_query_var( 'spokora_llms_hub' );
		if ( '1' === (string) $hub ) {
			$this->send_hub();
		}

		$slug = get_query_var( 'spokora_ai_doc_slug' );
		if ( is_string( $slug ) && $slug !== '' ) {
			$this->send_spoke( $slug );
		}

		// Fallback when rewrite rules are not flushed yet.
		$path = function_exists( 'spokora_request_path' ) ? spokora_request_path() : '';
		if ( ! is_string( $path ) ) {
			return;
		}
		$path = '/' . ltrim( $path, '/' );
		if ( '/llms.txt' === $path ) {
			$this->send_hub();
		}
		if ( preg_match( '#^/ai-docs/([^/]+)\.md$#', $path, $m ) ) {
			$this->send_spoke( rawurldecode( $m[1] ) );
		}
	}

	private function base_url() {
		return rtrim( home_url(), '/' );
	}

	private function send_plain( $body, $status = 200, $content_type = 'text/plain; charset=utf-8' ) {
		if ( ! headers_sent() ) {
			status_header( $status );
			header( 'Content-Type: ' . $content_type );
			header( 'X-Robots-Tag: noindex' );
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text/md output.
		exit;
	}

	private function md_escape( $s ) {
		return str_replace( array( '[', ']', '(', ')' ), array( '\\[', '\\]', '\\(', '\\)' ), (string) $s );
	}

	private function get_preamble() {
		$options  = get_option( 'spokora_options', array() );
		$preamble = is_array( $options ) ? (string) ( $options['llms_preamble'] ?? '' ) : '';
		$preamble = trim( $preamble );
		if ( $preamble === '' ) {
			return '';
		}
		return str_replace( '{{BASE_URL}}', $this->base_url(), $preamble );
	}

	/**
	 * @return array<int, array{term: string, definition: string}>
	 */
	private function get_brand_vocabulary() {
		$options = get_option( 'spokora_options', array() );
		$raw     = is_array( $options ) ? (string) ( $options['llms_brand_vocab'] ?? '' ) : '';
		$rows    = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || strpos( $line, '|' ) === false ) {
				continue;
			}
			list( $term, $def ) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( $term === '' ) {
				continue;
			}
			$rows[] = array(
				'term'       => $term,
				'definition' => $def,
			);
		}
		return $rows;
	}

	/**
	 * Published items with public AI doc flag.
	 *
	 * @return WP_Post[]
	 */
	private function query_public_docs() {
		$q = new WP_Query(
			array(
				'post_type'      => spokora_llms_supported_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => '_spokora_llms_public',
						'value' => '1',
					),
				),
			)
		);
		return $q->posts;
	}

	private function summary_for_post( WP_Post $post ) {
		$summary = trim( (string) get_post_meta( $post->ID, '_spokora_llms_summary', true ) );
		if ( $summary !== '' ) {
			return $summary;
		}
		$excerpt = trim( (string) $post->post_excerpt );
		if ( $excerpt !== '' ) {
			return $excerpt;
		}
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 24, '…' );
	}

	private function send_hub() {
		$base      = $this->base_url();
		$site_name = get_bloginfo( 'name' );
		$tagline   = get_bloginfo( 'description' );

		$out   = array();
		$out[] = '# ' . $site_name;
		$out[] = '';
		if ( $tagline !== '' ) {
			$out[] = '> ' . $tagline;
			$out[] = '';
		}

		$preamble = $this->get_preamble();
		if ( $preamble !== '' ) {
			$out[] = $preamble;
			$out[] = '';
		}

		if ( function_exists( 'spokora_llms_hub_use_generated_sections' ) && ! spokora_llms_hub_use_generated_sections() ) {
			$out   = spokora_llms_minimal_machine_footer( $out, $base );
			$out   = apply_filters( 'spokora_llms_hub_lines', $out, $base );
			$this->send_plain( implode( "\n", $out ) . "\n" );
			return;
		}

		$out[] = '## Deep-Dive AI Documentation';
		$out[] = 'One hop from this index: plain Markdown at `/ai-docs/{slug}.md` for each item below.';
		$out[] = '';

		$docs = $this->query_public_docs();
		if ( $docs ) {
			foreach ( $docs as $post ) {
				$slug    = $post->post_name;
				$title   = get_the_title( $post );
				$summary = $this->summary_for_post( $post );
				$url     = $base . '/ai-docs/' . rawurlencode( $slug ) . '.md';
				$out[]   = '- [' . $this->md_escape( $title ) . '](' . $url . '): ' . $this->md_escape( $summary );
			}
		} else {
			$out[] = '- (No public AI documents yet. Publish content and enable **Public AI document** in the editor.)';
		}

		$brand = $this->get_brand_vocabulary();
		if ( $brand ) {
			$out[] = '';
			$out[] = '## Brand vocabulary';
			$out[] = 'Canonical terms used across this site:';
			$out[] = '';
			foreach ( $brand as $row ) {
				$out[] = '- **' . $this->md_escape( $row['term'] ) . '**: ' . $this->md_escape( $row['definition'] );
			}
		}

		$out[] = '';
		$out[] = '## Human website';
		$out[] = '- [Home](' . $base . '/)';
		$options = get_option( 'spokora_options', array() );
		$contact = is_array( $options ) ? trim( (string) ( $options['llms_contact_url'] ?? '' ) ) : '';
		if ( $contact !== '' ) {
			if ( strpos( $contact, 'http' ) !== 0 ) {
				$contact = home_url( $contact );
			}
			$out[] = '- [Contact](' . esc_url_raw( $contact ) . ')';
		}
		$out[] = '- [Sitemap](' . $base . '/sitemap.xml)';
		$out[] = '- [Robots](' . $base . '/robots.txt)';
		$out[] = '- [LLMS hub](' . $base . '/llms.txt)';

		$out = apply_filters( 'spokora_llms_hub_lines', $out, $base );

		$this->send_plain( implode( "\n", $out ) . "\n" );
	}

	/**
	 * @param string $slug Post slug.
	 */
	private function send_spoke( $slug ) {
		$slug = sanitize_title( $slug );
		if ( $slug === '' ) {
			$this->send_plain( "Not found\n", 404 );
		}

		$posts = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => spokora_llms_supported_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_spokora_llms_public',
						'value' => '1',
					),
				),
			)
		);

		if ( ! $posts ) {
			$this->send_plain( "Not found\n", 404 );
		}

		$post = $posts[0];
		$body = trim( (string) get_post_meta( $post->ID, '_spokora_llms_md', true ) );
		if ( $body === '' ) {
			$body = wp_strip_all_tags( $post->post_content );
			$body = trim( preg_replace( '/\n{3,}/', "\n\n", $body ) ?? $body );
		}

		$title = get_the_title( $post );
		$out   = array();
		$out[] = '# ' . $title;
		$out[] = '';
		$out[] = '<!-- updated_at: ' . gmdate( 'c', strtotime( $post->post_modified_gmt ) ) . ' -->';
		$out[] = '';
		$out[] = $body;
		$out[] = '';

		$permalink = get_permalink( $post );
		if ( is_string( $permalink ) && $permalink !== '' ) {
			$out[] = '';
			$out[] = '## Human page';
			$out[] = '';
			$out[] = 'Canonical HTML: `' . $permalink . '`';
			$out[] = '';
		}

		$this->send_plain( implode( "\n", $out ) . "\n", 200, 'text/markdown; charset=utf-8' );
	}

	public function add_meta_boxes() {
		foreach ( spokora_llms_supported_post_types() as $screen ) {
			add_meta_box(
				'spokora_llms_meta_box',
				__( 'AI / LLM documentation', 'spokora-seo-toolkit' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'normal',
				'default'
			);
		}
	}

	/**
	 * @param WP_Post $post Post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'spokora_llms_save_meta', 'spokora_llms_meta_nonce' );

		$public  = get_post_meta( $post->ID, '_spokora_llms_public', true ) === '1';
		$include = get_post_meta( $post->ID, '_spokora_llms_include', true ) === '1';
		$summary = get_post_meta( $post->ID, '_spokora_llms_summary', true );
		$md      = get_post_meta( $post->ID, '_spokora_llms_md', true );
		$slug    = $post->post_name;
		$preview = $slug ? home_url( '/ai-docs/' . rawurlencode( $slug ) . '.md' ) : '';

		echo '<p class="description">' . esc_html__( 'Public AI documents appear in /llms.txt and at /ai-docs/{slug}.md when published.', 'spokora-seo-toolkit' ) . '</p>';

		$model_ok = function_exists( 'spokora_model_is_configured' ) && spokora_model_is_configured();
		if ( $model_ok ) {
			echo '<p class="spokora-llms-ai-actions" style="margin:12px 0 16px;">';
			echo '<button type="button" id="spokora-llms-ai-trigger" class="button button-primary" style="background:#1a73e8;border-color:#1a73e8;">';
			echo '<span class="dashicons dashicons-superhero" style="margin-top:3px;"></span> ';
			echo esc_html__( 'Draft with the model', 'spokora-seo-toolkit' );
			echo '</button>';
			echo '<span id="spokora-llms-ai-status" class="description" style="display:block;margin-top:8px;"></span>';
			echo '<span class="description" style="display:block;margin-top:4px;">';
			echo esc_html__( 'Uses the page title and editor content (or bundled HTML) to fill the summary and markdown below.', 'spokora-seo-toolkit' );
			echo '</span></p>';
		} elseif ( function_exists( 'spokora_maybe_render_gemini_vault_hint' ) ) {
			echo '<div style="margin:12px 0 16px;">';
			spokora_maybe_render_gemini_vault_hint();
			echo '</div>';
		}

		echo '<p><label><input type="checkbox" name="spokora_llms_public" value="1" ' . checked( $public, true, false ) . ' /> ';
		echo esc_html__( 'Public AI document', 'spokora-seo-toolkit' ) . '</label></p>';

		echo '<p><label><input type="checkbox" name="spokora_llms_include" value="1" ' . checked( $include || $public, true, false ) . ' /> ';
		echo esc_html__( 'Include in llms hub index', 'spokora-seo-toolkit' ) . '</label></p>';

		echo '<p><label for="spokora_llms_summary"><strong>' . esc_html__( 'Summary (one line for llms.txt)', 'spokora-seo-toolkit' ) . '</strong></label><br />';
		echo '<input type="text" id="spokora_llms_summary" name="spokora_llms_summary" value="' . esc_attr( $summary ) . '" style="width:100%;margin-top:4px;" /></p>';

		echo '<p><label for="spokora_llms_md"><strong>' . esc_html__( 'Markdown body (/ai-docs/{slug}.md)', 'spokora-seo-toolkit' ) . '</strong></label><br />';
		echo '<textarea id="spokora_llms_md" name="spokora_llms_md" rows="12" style="width:100%;margin-top:4px;font-family:monospace;">' . esc_textarea( $md ) . '</textarea></p>';

		if ( $preview && $public ) {
			echo '<p><a href="' . esc_url( $preview ) . '" target="_blank" rel="noopener">' . esc_html__( 'Preview AI doc', 'spokora-seo-toolkit' ) . '</a></p>';
		}
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function save_post_meta( $post_id ) {
		if ( ! isset( $_POST['spokora_llms_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spokora_llms_meta_nonce'] ) ), 'spokora_llms_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( ! in_array( $type, spokora_llms_supported_post_types(), true ) ) {
			return;
		}

		$public = ! empty( $_POST['spokora_llms_public'] ) ? '1' : '0';
		update_post_meta( $post_id, '_spokora_llms_public', $public );

		$include = ! empty( $_POST['spokora_llms_include'] ) ? '1' : '0';
		if ( '1' === $public ) {
			$include = '1';
		}
		update_post_meta( $post_id, '_spokora_llms_include', $include );

		if ( isset( $_POST['spokora_llms_summary'] ) ) {
			update_post_meta( $post_id, '_spokora_llms_summary', sanitize_text_field( wp_unslash( $_POST['spokora_llms_summary'] ) ) );
		}
		if ( isset( $_POST['spokora_llms_md'] ) ) {
			update_post_meta( $post_id, '_spokora_llms_md', sanitize_textarea_field( wp_unslash( $_POST['spokora_llms_md'] ) ) );
		}
	}

	public function admin_menu() {
		add_submenu_page(
			spokora_admin_menu_slug(),
			__( 'AI docs', 'spokora-seo-toolkit' ),
			__( 'AI docs', 'spokora-seo-toolkit' ),
			'manage_options',
			'spokora-llms',
			array( $this, 'render_settings_page' )
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options = get_option( 'spokora_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$base      = $this->base_url();
		$ai_public = function_exists( 'spokora_count_public_ai_docs' ) ? spokora_count_public_ai_docs() : 0;
		$md_url    = admin_url( 'admin.php?page=spokora-markdown' );
		?>
		<div class="wrap" style="max-width:880px;">
			<?php spokora_render_subnav( 'llms' ); ?>
			<h1><?php esc_html_e( 'AI docs', 'spokora-seo-toolkit' ); ?></h1>
			<?php
			if ( function_exists( 'spokora_render_pro_teaser' ) ) {
				spokora_render_pro_teaser( 'keys' );
			}
			?>
			<div class="spokora-help__card" style="margin:16px 0 24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'What this is', 'spokora-seo-toolkit' ); ?></h2>
				<p><?php esc_html_e( 'Agent-ready documentation has two layers on every site:', 'spokora-seo-toolkit' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Hub — /llms.txt', 'spokora-seo-toolkit' ); ?></strong> — <?php esc_html_e( 'One index file crawlers read first. You edit the preamble and vocabulary on this screen.', 'spokora-seo-toolkit' ); ?></li>
					<li><strong><?php esc_html_e( 'Spokes — /ai-docs/{slug}.md', 'spokora-seo-toolkit' ); ?></strong> — <?php esc_html_e( 'Optional markdown copies of individual pages you flag as public AI documents in the editor.', 'spokora-seo-toolkit' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Plain /md/{slug}.md mirrors (every published URL) are separate — see Results → Markdown library.', 'spokora-seo-toolkit' ); ?> <a href="<?php echo esc_url( $md_url ); ?>"><?php esc_html_e( 'Open markdown library', 'spokora-seo-toolkit' ); ?></a></p>
			</div>
			<div class="spokora-help__card" style="margin-bottom:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Agency workflow', 'spokora-seo-toolkit' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Save permalinks once so /llms.txt resolves.', 'spokora-seo-toolkit' ); ?></li>
					<li><?php esc_html_e( 'Write the hub preamble and brand vocabulary below (or lock gold copy from a template site).', 'spokora-seo-toolkit' ); ?></li>
					<li><?php esc_html_e( 'On each important page, open AI / LLM documentation in the editor → turn on “Public AI document” when that URL should have its own spoke.', 'spokora-seo-toolkit' ); ?></li>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: count of public AI docs */
								_n( '%d public spoke is live now.', '%d public spokes are live now.', (int) $ai_public, 'spokora-seo-toolkit' ),
								(int) $ai_public
							)
						);
						?>
					</li>
				</ol>
			</div>
			<h2><?php esc_html_e( 'Hub settings (/llms.txt)', 'spokora-seo-toolkit' ); ?></h2>
			<p>
				<?php esc_html_e( 'Preview:', 'spokora-seo-toolkit' ); ?>
				<a href="<?php echo esc_url( $base . '/llms.txt' ); ?>" target="_blank" rel="noopener"><code>/llms.txt</code></a>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'spokora_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Hub preamble', 'spokora-seo-toolkit' ); ?></th>
						<td>
							<textarea name="spokora_options[llms_preamble]" rows="14" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $options['llms_preamble'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Markdown-ish plain text inserted after site title and tagline. Use {{BASE_URL}} for absolute links. Fleet gold copy: lock preamble and turn off generated sections below.', 'spokora-seo-toolkit' ); ?></p>
							<p>
								<input type="hidden" name="spokora_options[llms_preamble_locked]" value="0" />
								<label><input type="checkbox" name="spokora_options[llms_preamble_locked]" value="1" <?php checked( isset( $options['llms_preamble_locked'] ) && (string) $options['llms_preamble_locked'] === '1' ); ?> />
								<?php esc_html_e( 'Lock preamble & brand vocabulary (block accidental clear on save)', 'spokora-seo-toolkit' ); ?></label>
							</p>
							<p>
								<input type="hidden" name="spokora_options[llms_hub_generated_sections]" value="0" />
								<label><input type="checkbox" name="spokora_options[llms_hub_generated_sections]" value="1" <?php checked( ! isset( $options['llms_hub_generated_sections'] ) || (string) $options['llms_hub_generated_sections'] !== '0' ); ?> />
								<?php esc_html_e( 'Append plugin-generated hub sections (Deep-Dive, vocabulary, Human website)', 'spokora-seo-toolkit' ); ?></label>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Brand vocabulary', 'spokora-seo-toolkit' ); ?></th>
						<td>
							<textarea name="spokora_options[llms_brand_vocab]" rows="8" style="width:100%;font-family:monospace;" placeholder="Term|Definition&#10;Another term|What it means"><?php echo esc_textarea( $options['llms_brand_vocab'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One term per line: Term|Definition', 'spokora-seo-toolkit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Contact URL (Human website)', 'spokora-seo-toolkit' ); ?></th>
						<td>
							<input type="text" name="spokora_options[llms_contact_url]" value="<?php echo esc_attr( $options['llms_contact_url'] ?? '' ); ?>" style="width:100%;" placeholder="/contact or https://example.com/contact" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save LLMS settings', 'spokora-seo-toolkit' ) ); ?>
			</form>
		</div>
		<?php
	}
}

new Spokora_Llms_Engine();

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bracket_PO_Settings {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_settings_assets' ] );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public static function add_menu_page() {
		add_options_page(
			__( 'Bracket Post Order', 'bracket-post-order' ),
			__( 'Bracket Post Order', 'bracket-post-order' ),
			'manage_options',
			'bracket-post-order',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue settings page CSS and JS.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_settings_assets( $hook ) {
		if ( $hook !== 'settings_page_bracket-post-order' ) {
			return;
		}

		wp_enqueue_style(
			'bracket-po-settings',
			BRACKET_PO_URL . 'assets/css/bracket-po-settings.css',
			[],
			BRACKET_PO_VERSION
		);

		wp_enqueue_script(
			'bracket-po-settings',
			BRACKET_PO_URL . 'assets/js/bracket-po-settings.js',
			[],
			BRACKET_PO_VERSION,
			true
		);
	}

	/**
	 * Register settings with the Settings API (for save/sanitize only).
	 */
	public static function register_settings() {
		register_setting( 'bracket_po_settings_group', 'bracket_po_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
			'default'           => [
				'post_types'  => [],
				'taxonomies'  => [],
				'term_order'  => [],
			],
		] );
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = [
			'post_types'  => [],
			'taxonomies'  => [],
			'term_order'  => [],
		];

		if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
		}

		if ( ! empty( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			$sanitized['taxonomies'] = array_map( 'sanitize_key', $input['taxonomies'] );
		}

		if ( ! empty( $input['term_order'] ) && is_array( $input['term_order'] ) ) {
			$sanitized['term_order'] = array_map( 'sanitize_key', $input['term_order'] );
		}

		// Initialize menu_order for newly enabled post types.
		$old_settings = Bracket_PO_Core::get_settings();
		$old_pts      = ! empty( $old_settings['post_types'] ) ? (array) $old_settings['post_types'] : [];
		$new_pts      = $sanitized['post_types'];

		foreach ( $new_pts as $pt ) {
			if ( ! in_array( $pt, $old_pts, true ) ) {
				Bracket_PO_Core::initialize_post_type_order( $pt );
			}
		}

		return $sanitized;
	}

	/**
	 * Get post types available for ordering.
	 *
	 * @return array
	 */
	private static function get_available_post_types() {
		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
		$excluded   = [ 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face' ];

		return array_filter( $post_types, function( $pt ) use ( $excluded ) {
			return ! in_array( $pt->name, $excluded, true );
		} );
	}

	/**
	 * Get all taxonomies with their associated post types.
	 *
	 * @return array
	 */
	private static function get_all_taxonomies_with_post_types() {
		$post_types = self::get_available_post_types();
		$pt_slugs   = array_keys( $post_types );
		$taxonomies = get_taxonomies( [ 'show_ui' => true ], 'objects' );
		$result     = [];

		foreach ( $taxonomies as $tax ) {
			$intersect = array_intersect( $tax->object_type, $pt_slugs );
			if ( ! empty( $intersect ) ) {
				$tax->_bracket_po_post_types = array_values( $intersect );
				$result[ $tax->name ] = $tax;
			}
		}

		return $result;
	}

	/**
	 * Get all taxonomies available for term reordering.
	 *
	 * @return array
	 */
	private static function get_all_taxonomies_for_term_order() {
		$taxonomies = get_taxonomies( [ 'show_ui' => true ], 'objects' );
		$excluded   = [ 'post_format', 'wp_theme', 'wp_template_part_area' ];

		return array_filter( $taxonomies, function( $tax ) use ( $excluded ) {
			return ! in_array( $tax->name, $excluded, true );
		} );
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		$settings        = Bracket_PO_Core::get_settings();
		$enabled_pts     = $settings['post_types'];
		$enabled_taxs    = $settings['taxonomies'];
		$enabled_torder  = $settings['term_order'];

		$post_types       = self::get_available_post_types();
		$taxonomies       = self::get_all_taxonomies_with_post_types();
		$term_order_taxes = self::get_all_taxonomies_for_term_order();
		?>
		<div class="bracket-po-settings-wrap">

			<div class="bracket-po-header">
				<div class="bracket-po-header__inner">
					<div class="bracket-po-header__title">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect x="3" y="4" width="18" height="3" rx="1" fill="currentColor" opacity="0.9"/>
							<rect x="3" y="10.5" width="14" height="3" rx="1" fill="currentColor" opacity="0.6"/>
							<rect x="3" y="17" width="10" height="3" rx="1" fill="currentColor" opacity="0.35"/>
						</svg>
						<h1><?php esc_html_e( 'Bracket Post Order', 'bracket-post-order' ); ?></h1>
					</div>
					<span class="bracket-po-header__version"><?php echo esc_html( BRACKET_PO_VERSION ); ?></span>
				</div>
			</div>

			<form method="post" action="options.php" class="bracket-po-form">
				<?php settings_fields( 'bracket_po_settings_group' ); ?>

				<div class="bracket-po-grid">

					<!-- Post Types Card -->
					<div class="bracket-po-card">
						<div class="bracket-po-card__header">
							<div class="bracket-po-card__icon bracket-po-card__icon--blue">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								</svg>
							</div>
							<div>
								<h2 class="bracket-po-card__title"><?php esc_html_e( 'Post Types', 'bracket-post-order' ); ?></h2>
								<p class="bracket-po-card__subtitle"><?php esc_html_e( 'Global drag-and-drop ordering via menu_order', 'bracket-post-order' ); ?></p>
							</div>
						</div>
						<div class="bracket-po-card__body">
							<?php foreach ( $post_types as $pt ) :
								$checked = in_array( $pt->name, $enabled_pts, true );
								$id = 'bracket-po-pt-' . $pt->name;
							?>
								<label class="bracket-po-toggle" for="<?php echo esc_attr( $id ); ?>">
									<div class="bracket-po-toggle__text">
										<span class="bracket-po-toggle__label"><?php echo esc_html( $pt->label ); ?></span>
										<span class="bracket-po-toggle__slug"><?php echo esc_html( $pt->name ); ?></span>
									</div>
									<div class="bracket-po-toggle__switch">
										<input
											type="checkbox"
											id="<?php echo esc_attr( $id ); ?>"
											name="bracket_po_settings[post_types][]"
											value="<?php echo esc_attr( $pt->name ); ?>"
											<?php checked( $checked ); ?>
										/>
										<span class="bracket-po-toggle__slider"></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Per-Term Post Ordering Card -->
					<div class="bracket-po-card">
						<div class="bracket-po-card__header">
							<div class="bracket-po-card__icon bracket-po-card__icon--purple">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
									<rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
									<path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								</svg>
							</div>
							<div>
								<h2 class="bracket-po-card__title"><?php esc_html_e( 'Per-Term Post Ordering', 'bracket-post-order' ); ?></h2>
								<p class="bracket-po-card__subtitle"><?php esc_html_e( 'Different post order within each taxonomy term', 'bracket-post-order' ); ?></p>
							</div>
						</div>
						<div class="bracket-po-card__body" id="bracket-po-tax-list">
							<div class="bracket-po-empty" id="bracket-po-tax-empty">
								<p><?php esc_html_e( 'Enable at least one post type above to see available taxonomies.', 'bracket-post-order' ); ?></p>
							</div>
							<?php foreach ( $taxonomies as $tax ) :
								$checked = in_array( $tax->name, $enabled_taxs, true );
								$id = 'bracket-po-tax-' . $tax->name;
								$pt_labels = array_map( function( $pt_slug ) {
									$obj = get_post_type_object( $pt_slug );
									return $obj ? $obj->label : $pt_slug;
								}, $tax->_bracket_po_post_types );
							?>
								<label
									class="bracket-po-toggle bracket-po-tax-toggle"
									for="<?php echo esc_attr( $id ); ?>"
									data-post-types="<?php echo esc_attr( implode( ',', $tax->_bracket_po_post_types ) ); ?>"
								>
									<div class="bracket-po-toggle__text">
										<span class="bracket-po-toggle__label"><?php echo esc_html( $tax->label ); ?></span>
										<span class="bracket-po-toggle__slug"><?php echo esc_html( $tax->name ); ?> &middot; <?php echo esc_html( implode( ', ', $pt_labels ) ); ?></span>
									</div>
									<div class="bracket-po-toggle__switch">
										<input
											type="checkbox"
											id="<?php echo esc_attr( $id ); ?>"
											name="bracket_po_settings[taxonomies][]"
											value="<?php echo esc_attr( $tax->name ); ?>"
											<?php checked( $checked ); ?>
										/>
										<span class="bracket-po-toggle__slider"></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Taxonomy Term Ordering Card -->
					<div class="bracket-po-card">
						<div class="bracket-po-card__header">
							<div class="bracket-po-card__icon bracket-po-card__icon--green">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M7 7h10M7 12h10M7 17h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
									<path d="M4 7h.01M4 12h.01M4 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
								</svg>
							</div>
							<div>
								<h2 class="bracket-po-card__title"><?php esc_html_e( 'Term Ordering', 'bracket-post-order' ); ?></h2>
								<p class="bracket-po-card__subtitle"><?php esc_html_e( 'Reorder taxonomy terms via drag-and-drop', 'bracket-post-order' ); ?></p>
							</div>
						</div>
						<div class="bracket-po-card__body">
							<?php foreach ( $term_order_taxes as $tax ) :
								$checked = in_array( $tax->name, $enabled_torder, true );
								$id = 'bracket-po-to-' . $tax->name;
							?>
								<label class="bracket-po-toggle" for="<?php echo esc_attr( $id ); ?>">
									<div class="bracket-po-toggle__text">
										<span class="bracket-po-toggle__label"><?php echo esc_html( $tax->label ); ?></span>
										<span class="bracket-po-toggle__slug"><?php echo esc_html( $tax->name ); ?></span>
									</div>
									<div class="bracket-po-toggle__switch">
										<input
											type="checkbox"
											id="<?php echo esc_attr( $id ); ?>"
											name="bracket_po_settings[term_order][]"
											value="<?php echo esc_attr( $tax->name ); ?>"
											<?php checked( $checked ); ?>
										/>
										<span class="bracket-po-toggle__slider"></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

				</div><!-- .bracket-po-grid -->

				<div class="bracket-po-actions">
					<?php submit_button( __( 'Save Changes', 'bracket-post-order' ), 'primary bracket-po-save-btn', 'submit', false ); ?>
				</div>

			</form>
		</div>
		<?php
	}
}

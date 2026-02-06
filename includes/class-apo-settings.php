<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APO_Settings {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public static function add_menu_page() {
		$hook = add_options_page(
			__( 'Advanced Post Order', 'advanced-post-order' ),
			__( 'Advanced Post Order', 'advanced-post-order' ),
			'manage_options',
			'advanced-post-order',
			[ __CLASS__, 'render_page' ]
		);

		add_action( "admin_print_styles-{$hook}", [ __CLASS__, 'print_styles' ] );
	}

	/**
	 * Register settings with the Settings API (for save/sanitize only).
	 */
	public static function register_settings() {
		register_setting( 'apo_settings_group', 'apo_settings', [
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
		$old_settings = APO_Core::get_settings();
		$old_pts      = ! empty( $old_settings['post_types'] ) ? (array) $old_settings['post_types'] : [];
		$new_pts      = $sanitized['post_types'];

		foreach ( $new_pts as $pt ) {
			if ( ! in_array( $pt, $old_pts, true ) ) {
				APO_Core::initialize_post_type_order( $pt );
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
				$tax->_apo_post_types = array_values( $intersect );
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
		$settings        = APO_Core::get_settings();
		$enabled_pts     = $settings['post_types'];
		$enabled_taxs    = $settings['taxonomies'];
		$enabled_torder  = $settings['term_order'];

		$post_types       = self::get_available_post_types();
		$taxonomies       = self::get_all_taxonomies_with_post_types();
		$term_order_taxes = self::get_all_taxonomies_for_term_order();
		?>
		<div class="apo-settings-wrap">

			<div class="apo-header">
				<div class="apo-header__inner">
					<div class="apo-header__title">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect x="3" y="4" width="18" height="3" rx="1" fill="currentColor" opacity="0.9"/>
							<rect x="3" y="10.5" width="14" height="3" rx="1" fill="currentColor" opacity="0.6"/>
							<rect x="3" y="17" width="10" height="3" rx="1" fill="currentColor" opacity="0.35"/>
						</svg>
						<h1><?php esc_html_e( 'Advanced Post Order', 'advanced-post-order' ); ?></h1>
					</div>
					<span class="apo-header__version"><?php echo esc_html( APO_VERSION ); ?></span>
				</div>
			</div>

			<form method="post" action="options.php" class="apo-form">
				<?php settings_fields( 'apo_settings_group' ); ?>

				<div class="apo-grid">

					<!-- Post Types Card -->
					<div class="apo-card">
						<div class="apo-card__header">
							<div class="apo-card__icon apo-card__icon--blue">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								</svg>
							</div>
							<div>
								<h2 class="apo-card__title"><?php esc_html_e( 'Post Types', 'advanced-post-order' ); ?></h2>
								<p class="apo-card__subtitle"><?php esc_html_e( 'Global drag-and-drop ordering via menu_order', 'advanced-post-order' ); ?></p>
							</div>
						</div>
						<div class="apo-card__body">
							<?php foreach ( $post_types as $pt ) :
								$checked = in_array( $pt->name, $enabled_pts, true );
								$id = 'apo-pt-' . $pt->name;
							?>
								<label class="apo-toggle" for="<?php echo esc_attr( $id ); ?>">
									<div class="apo-toggle__text">
										<span class="apo-toggle__label"><?php echo esc_html( $pt->label ); ?></span>
										<span class="apo-toggle__slug"><?php echo esc_html( $pt->name ); ?></span>
									</div>
									<div class="apo-toggle__switch">
										<input
											type="checkbox"
											id="<?php echo esc_attr( $id ); ?>"
											name="apo_settings[post_types][]"
											value="<?php echo esc_attr( $pt->name ); ?>"
											<?php checked( $checked ); ?>
										/>
										<span class="apo-toggle__slider"></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Per-Term Post Ordering Card -->
					<div class="apo-card">
						<div class="apo-card__header">
							<div class="apo-card__icon apo-card__icon--purple">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
									<rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
									<path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								</svg>
							</div>
							<div>
								<h2 class="apo-card__title"><?php esc_html_e( 'Per-Term Post Ordering', 'advanced-post-order' ); ?></h2>
								<p class="apo-card__subtitle"><?php esc_html_e( 'Different post order within each taxonomy term', 'advanced-post-order' ); ?></p>
							</div>
						</div>
						<div class="apo-card__body" id="apo-tax-list">
							<div class="apo-empty" id="apo-tax-empty">
								<p><?php esc_html_e( 'Enable at least one post type above to see available taxonomies.', 'advanced-post-order' ); ?></p>
							</div>
							<?php foreach ( $taxonomies as $tax ) :
								$checked = in_array( $tax->name, $enabled_taxs, true );
								$id = 'apo-tax-' . $tax->name;
								$pt_labels = array_map( function( $pt_slug ) {
									$obj = get_post_type_object( $pt_slug );
									return $obj ? $obj->label : $pt_slug;
								}, $tax->_apo_post_types );
							?>
								<label
									class="apo-toggle apo-tax-toggle"
									for="<?php echo esc_attr( $id ); ?>"
									data-post-types="<?php echo esc_attr( implode( ',', $tax->_apo_post_types ) ); ?>"
								>
									<div class="apo-toggle__text">
										<span class="apo-toggle__label"><?php echo esc_html( $tax->label ); ?></span>
										<span class="apo-toggle__slug"><?php echo esc_html( $tax->name ); ?> &middot; <?php echo esc_html( implode( ', ', $pt_labels ) ); ?></span>
									</div>
									<div class="apo-toggle__switch">
										<input
											type="checkbox"
											id="<?php echo esc_attr( $id ); ?>"
											name="apo_settings[taxonomies][]"
											value="<?php echo esc_attr( $tax->name ); ?>"
											<?php checked( $checked ); ?>
										/>
										<span class="apo-toggle__slider"></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Taxonomy Term Ordering Card -->
					<div class="apo-card">
						<div class="apo-card__header">
							<div class="apo-card__icon apo-card__icon--green">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M7 7h10M7 12h10M7 17h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
									<path d="M4 7h.01M4 12h.01M4 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
								</svg>
							</div>
							<div>
								<h2 class="apo-card__title"><?php esc_html_e( 'Term Ordering', 'advanced-post-order' ); ?></h2>
								<p class="apo-card__subtitle"><?php esc_html_e( 'Reorder taxonomy terms via drag-and-drop', 'advanced-post-order' ); ?></p>
							</div>
						</div>
						<div class="apo-card__body">
							<?php foreach ( $term_order_taxes as $tax ) :
								$checked = in_array( $tax->name, $enabled_torder, true );
								$id = 'apo-to-' . $tax->name;
							?>
								<label class="apo-toggle" for="<?php echo esc_attr( $id ); ?>">
									<div class="apo-toggle__text">
										<span class="apo-toggle__label"><?php echo esc_html( $tax->label ); ?></span>
										<span class="apo-toggle__slug"><?php echo esc_html( $tax->name ); ?></span>
									</div>
									<div class="apo-toggle__switch">
										<input
											type="checkbox"
											id="<?php echo esc_attr( $id ); ?>"
											name="apo_settings[term_order][]"
											value="<?php echo esc_attr( $tax->name ); ?>"
											<?php checked( $checked ); ?>
										/>
										<span class="apo-toggle__slider"></span>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

				</div><!-- .apo-grid -->

				<div class="apo-actions">
					<?php submit_button( __( 'Save Changes', 'advanced-post-order' ), 'primary apo-save-btn', 'submit', false ); ?>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Print settings page styles.
	 */
	public static function print_styles() {
		?>
		<style>
			/* Reset WP admin defaults for our page */
			.apo-settings-wrap {
				max-width: 860px;
				margin: 0;
			}

			/* Header */
			.apo-header {
				background: #1d2327;
				margin: -1px -1px 0 -20px;
				padding: 0 20px;
			}
			.apo-header__inner {
				max-width: 860px;
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 18px 0;
			}
			.apo-header__title {
				display: flex;
				align-items: center;
				gap: 12px;
				color: #fff;
			}
			.apo-header__title h1 {
				color: #fff;
				font-size: 18px;
				font-weight: 500;
				margin: 0;
				padding: 0;
				letter-spacing: -0.01em;
			}
			.apo-header__title svg {
				flex-shrink: 0;
			}
			.apo-header__version {
				font-size: 11px;
				color: rgba(255,255,255,0.45);
				background: rgba(255,255,255,0.08);
				padding: 3px 8px;
				border-radius: 4px;
				font-weight: 500;
			}

			/* Grid */
			.apo-grid {
				display: grid;
				gap: 16px;
				margin-top: 24px;
			}

			/* Cards */
			.apo-card {
				background: #fff;
				border: 1px solid #dcdde1;
				border-radius: 8px;
				overflow: hidden;
			}
			.apo-card__header {
				display: flex;
				align-items: flex-start;
				gap: 14px;
				padding: 20px 24px 16px;
				border-bottom: 1px solid #f0f0f1;
			}
			.apo-card__icon {
				width: 40px;
				height: 40px;
				border-radius: 10px;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}
			.apo-card__icon--blue {
				background: #e7f0fe;
				color: #2271b1;
			}
			.apo-card__icon--purple {
				background: #f0e7fe;
				color: #7e3bd0;
			}
			.apo-card__icon--green {
				background: #e2f5ea;
				color: #1a8a42;
			}
			.apo-card__title {
				font-size: 14px;
				font-weight: 600;
				margin: 0 0 2px;
				padding: 0;
				color: #1d2327;
			}
			.apo-card__subtitle {
				font-size: 12.5px;
				color: #646970;
				margin: 0;
			}
			.apo-card__body {
				padding: 4px 0;
			}

			/* Toggle rows */
			.apo-toggle {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 12px 24px;
				cursor: pointer;
				transition: background-color 0.15s ease;
				border-bottom: 1px solid #f6f7f7;
				gap: 16px;
			}
			.apo-toggle:last-child {
				border-bottom: 0;
			}
			.apo-toggle:hover {
				background-color: #f9f9f9;
			}
			.apo-toggle__text {
				display: flex;
				flex-direction: column;
				gap: 1px;
				min-width: 0;
			}
			.apo-toggle__label {
				font-size: 13px;
				font-weight: 500;
				color: #1d2327;
			}
			.apo-toggle__slug {
				font-size: 11.5px;
				color: #898d91;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}

			/* Toggle switch */
			.apo-toggle__switch {
				position: relative;
				width: 38px;
				height: 22px;
				flex-shrink: 0;
			}
			.apo-toggle__switch input {
				opacity: 0;
				width: 0;
				height: 0;
				position: absolute;
			}
			.apo-toggle__slider {
				position: absolute;
				inset: 0;
				background-color: #c3c4c7;
				border-radius: 22px;
				transition: background-color 0.2s ease;
				cursor: pointer;
			}
			.apo-toggle__slider::before {
				content: "";
				position: absolute;
				height: 16px;
				width: 16px;
				left: 3px;
				bottom: 3px;
				background-color: #fff;
				border-radius: 50%;
				transition: transform 0.2s ease;
				box-shadow: 0 1px 3px rgba(0,0,0,0.15);
			}
			.apo-toggle__switch input:checked + .apo-toggle__slider {
				background-color: #2271b1;
			}
			.apo-toggle__switch input:checked + .apo-toggle__slider::before {
				transform: translateX(16px);
			}
			.apo-toggle__switch input:focus-visible + .apo-toggle__slider {
				outline: 2px solid #2271b1;
				outline-offset: 2px;
			}

			/* Empty state */
			.apo-empty {
				padding: 20px 24px;
			}
			.apo-empty p {
				margin: 0;
				color: #898d91;
				font-size: 13px;
				font-style: italic;
			}

			/* Taxonomy toggle visibility */
			.apo-tax-toggle {
				display: none;
			}
			.apo-tax-toggle.apo-visible {
				display: flex;
			}

			/* Actions bar */
			.apo-actions {
				margin-top: 20px;
				padding: 0;
			}
			.apo-save-btn.button.button-primary {
				padding: 4px 24px;
				height: 36px;
				font-size: 13px;
				border-radius: 6px;
			}
		</style>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var ptCheckboxes  = document.querySelectorAll('input[name="apo_settings[post_types][]"]');
			var taxToggles    = document.querySelectorAll('.apo-tax-toggle');
			var emptyMsg      = document.getElementById('apo-tax-empty');

			function updateTaxonomies() {
				var activePts = [];
				ptCheckboxes.forEach(function(cb) {
					if (cb.checked) activePts.push(cb.value);
				});

				var visibleCount = 0;
				taxToggles.forEach(function(toggle) {
					var pts = toggle.getAttribute('data-post-types').split(',');
					var match = pts.some(function(pt) { return activePts.indexOf(pt) !== -1; });
					toggle.classList.toggle('apo-visible', match);
					if (match) visibleCount++;
				});

				emptyMsg.style.display = visibleCount > 0 ? 'none' : '';
			}

			ptCheckboxes.forEach(function(cb) {
				cb.addEventListener('change', updateTaxonomies);
			});

			updateTaxonomies();
		});
		</script>
		<?php
	}
}

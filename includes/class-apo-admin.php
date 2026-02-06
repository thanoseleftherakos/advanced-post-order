<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APO_Admin {

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'refresh_order' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'admin_notices', [ __CLASS__, 'conflict_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'term_mode_notice' ] );
	}

	/**
	 * Refresh menu_order on admin_init for enabled post types.
	 *
	 * Detects gaps (new posts, deleted posts) and re-sequences
	 * so drag-and-drop always starts from a clean state.
	 */
	public static function refresh_order() {
		$enabled = APO_Core::get_enabled_post_types();
		foreach ( $enabled as $post_type ) {
			APO_Core::refresh_post_type_order( $post_type );
		}
	}

	/**
	 * Determine the current ordering mode on edit.php.
	 *
	 * @return string|false 'global', 'term', or false if not applicable.
	 */
	private static function get_mode() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'edit' ) {
			return false;
		}

		$post_type = $screen->post_type;
		$enabled_pts = APO_Core::get_enabled_post_types();

		if ( ! in_array( $post_type, $enabled_pts, true ) ) {
			return false;
		}

		// If user explicitly sorted via column header, disable drag-and-drop
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading column sort parameter from URL.
		if ( ! empty( $_GET['orderby'] ) ) {
			return false;
		}

		$tax_filter = APO_Core::is_taxonomy_filter_active();
		if ( $tax_filter ) {
			return 'term';
		}

		return 'global';
	}

	/**
	 * Enqueue scripts and styles on relevant admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		// Post list table (edit.php)
		if ( $hook === 'edit.php' ) {
			$mode = self::get_mode();
			if ( ! $mode ) {
				return;
			}

			// Dequeue SCPO scripts to prevent conflict
			wp_dequeue_script( 'scporder' );
			wp_dequeue_script( 'scpo-script' );

			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script(
				'apo-sortable',
				APO_URL . 'assets/js/apo-sortable.js',
				[ 'jquery', 'jquery-ui-sortable' ],
				APO_VERSION,
				true
			);

			$localize_data = [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'apo_nonce' ),
				'mode'     => $mode,
				'term_id'  => 0,
				'term_name' => '',
			];

			if ( $mode === 'term' ) {
				$tax_filter = APO_Core::is_taxonomy_filter_active();
				if ( $tax_filter ) {
					$localize_data['term_id']   = $tax_filter['term_id'];
					$localize_data['term_name'] = $tax_filter['term_name'];
				}
			}

			wp_localize_script( 'apo-sortable', 'apo_vars', $localize_data );

			wp_enqueue_style(
				'apo-admin',
				APO_URL . 'assets/css/apo-admin.css',
				[],
				APO_VERSION
			);

			return;
		}

		// Taxonomy term list (edit-tags.php)
		if ( $hook === 'edit-tags.php' ) {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$taxonomy = $screen->taxonomy;
			$enabled = APO_Core::get_enabled_term_order_taxonomies();

			if ( ! in_array( $taxonomy, $enabled, true ) ) {
				return;
			}

			// Dequeue SCPO scripts to prevent conflict
			wp_dequeue_script( 'scporder' );
			wp_dequeue_script( 'scpo-script' );

			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script(
				'apo-taxonomy',
				APO_URL . 'assets/js/apo-taxonomy.js',
				[ 'jquery', 'jquery-ui-sortable' ],
				APO_VERSION,
				true
			);

			wp_localize_script( 'apo-taxonomy', 'apo_tax_vars', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'apo_nonce' ),
			] );

			wp_enqueue_style(
				'apo-admin',
				APO_URL . 'assets/css/apo-admin.css',
				[],
				APO_VERSION
			);
		}
	}

	/**
	 * Show conflict notice if Simple Custom Post Order is active.
	 */
	public static function conflict_notice() {
		if ( ! class_exists( 'SCPO_Engine' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'edit', 'edit-tags', 'plugins' ], true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			__( '<strong>Advanced Post Order:</strong> Simple Custom Post Order is also active. To avoid conflicts, please deactivate Simple Custom Post Order.', 'advanced-post-order' ),
			[ 'strong' => [] ]
		);
		echo '</p></div>';
	}

	/**
	 * Show info notice when in term ordering mode.
	 */
	public static function term_mode_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'edit' ) {
			return;
		}

		$mode = self::get_mode();
		if ( $mode !== 'term' ) {
			return;
		}

		$tax_filter = APO_Core::is_taxonomy_filter_active();
		if ( ! $tax_filter ) {
			return;
		}

		$post_type_obj = get_post_type_object( $screen->post_type );
		$pt_label = $post_type_obj ? $post_type_obj->labels->name : $screen->post_type;

		printf(
			'<div class="notice notice-info apo-term-notice"><p>%s</p></div>',
			sprintf(
				/* translators: 1: post type label, 2: term name */
				esc_html__( 'Drag to reorder %1$s in "%2$s". Changes save automatically.', 'advanced-post-order' ),
				esc_html( $pt_label ),
				esc_html( $tax_filter['term_name'] )
			)
		);
	}

	/**
	 * Modify the admin query for ordering on edit.php.
	 * Hooked via pre_get_posts in APO_Query to keep query logic centralized.
	 */
}

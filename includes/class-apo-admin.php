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
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar_indicator' ], 999 );

		// Mark post type as stale when posts change.
		add_action( 'save_post', [ __CLASS__, 'mark_stale' ], 10, 2 );
		add_action( 'delete_post', [ __CLASS__, 'mark_stale_by_id' ] );
		add_action( 'wp_trash_post', [ __CLASS__, 'mark_stale_by_id' ] );

		// Order column for enabled post types.
		$enabled_pts = APO_Core::get_enabled_post_types();
		foreach ( $enabled_pts as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", [ __CLASS__, 'add_order_column' ] );
			add_action( "manage_{$pt}_posts_custom_column", [ __CLASS__, 'render_order_column' ], 10, 2 );
		}
	}

	/**
	 * Mark a post type as stale when a post is created, updated, or trashed.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function mark_stale( $post_id, $post ) {
		$enabled = APO_Core::get_enabled_post_types();
		if ( in_array( $post->post_type, $enabled, true ) ) {
			set_transient( 'apo_stale_' . $post->post_type, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Mark a post type as stale by post ID (for delete/trash hooks).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function mark_stale_by_id( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$enabled = APO_Core::get_enabled_post_types();
		if ( in_array( $post->post_type, $enabled, true ) ) {
			set_transient( 'apo_stale_' . $post->post_type, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Refresh menu_order on admin_init only for stale post types.
	 *
	 * Only runs when a transient flag exists, then deletes it.
	 * Eliminates 2 SQL queries per enabled post type on every admin page load.
	 */
	public static function refresh_order() {
		$enabled = APO_Core::get_enabled_post_types();
		foreach ( $enabled as $post_type ) {
			if ( get_transient( 'apo_stale_' . $post_type ) ) {
				APO_Core::refresh_post_type_order( $post_type );
				delete_transient( 'apo_stale_' . $post_type );
			}
		}
	}

	/**
	 * Determine the current ordering mode on edit.php.
	 *
	 * @return string|false 'global', 'term', or false if not applicable.
	 */
	private static function get_mode() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
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

			$screen = get_current_screen();

			// Dequeue SCPO scripts to prevent conflict
			wp_dequeue_script( 'scporder' );
			wp_dequeue_script( 'scpo-script' );

			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script(
				'apo-touch-punch',
				APO_URL . 'assets/js/jquery.ui.touch-punch.min.js',
				[ 'jquery-ui-sortable' ],
				APO_VERSION,
				true
			);
			wp_enqueue_script(
				'apo-sortable',
				APO_URL . 'assets/js/apo-sortable.js',
				[ 'jquery', 'jquery-ui-sortable', 'apo-touch-punch' ],
				APO_VERSION,
				true
			);

			$localize_data = [
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'apo_nonce' ),
				'mode'      => $mode,
				'term_id'   => 0,
				'term_name' => '',
				'post_type' => $screen->post_type,
				'i18n'      => [
					'order_saved'           => __( 'Order saved.', 'advanced-post-order' ),
					'undo'                  => __( 'Undo', 'advanced-post-order' ),
					'order_reverted'        => __( 'Order reverted.', 'advanced-post-order' ),
					'save_failed'           => __( 'Failed to save order.', 'advanced-post-order' ),
					'reset_confirm'         => __( 'Are you sure you want to reset the order? This cannot be undone.', 'advanced-post-order' ),
					'reset_success'         => __( 'Order has been reset.', 'advanced-post-order' ),
					'reset_failed'          => __( 'Failed to reset order.', 'advanced-post-order' ),
					'reset_order'           => __( 'Reset Order', 'advanced-post-order' ),
					'date_desc'             => __( 'Date (newest first)', 'advanced-post-order' ),
					'date_asc'              => __( 'Date (oldest first)', 'advanced-post-order' ),
					'title_asc'             => __( 'Title (A-Z)', 'advanced-post-order' ),
					'title_desc'            => __( 'Title (Z-A)', 'advanced-post-order' ),
					'keyboard_activated'    => __( 'Reorder mode activated. Use arrow keys to move, Enter to save, Escape to cancel.', 'advanced-post-order' ),
					'keyboard_moved_up'     => __( 'Moved up.', 'advanced-post-order' ),
					'keyboard_moved_down'   => __( 'Moved down.', 'advanced-post-order' ),
					'keyboard_saved'        => __( 'Position saved.', 'advanced-post-order' ),
					'keyboard_cancelled'    => __( 'Reorder cancelled.', 'advanced-post-order' ),
				],
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
				'apo-touch-punch',
				APO_URL . 'assets/js/jquery.ui.touch-punch.min.js',
				[ 'jquery-ui-sortable' ],
				APO_VERSION,
				true
			);
			wp_enqueue_script(
				'apo-taxonomy',
				APO_URL . 'assets/js/apo-taxonomy.js',
				[ 'jquery', 'jquery-ui-sortable', 'apo-touch-punch' ],
				APO_VERSION,
				true
			);

			wp_localize_script( 'apo-taxonomy', 'apo_tax_vars', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'apo_nonce' ),
				'i18n'     => [
					'order_saved'         => __( 'Term order saved.', 'advanced-post-order' ),
					'undo'                => __( 'Undo', 'advanced-post-order' ),
					'order_reverted'      => __( 'Order reverted.', 'advanced-post-order' ),
					'save_failed'         => __( 'Failed to save term order.', 'advanced-post-order' ),
					'keyboard_activated'  => __( 'Reorder mode activated. Use arrow keys to move, Enter to save, Escape to cancel.', 'advanced-post-order' ),
					'keyboard_moved_up'   => __( 'Moved up.', 'advanced-post-order' ),
					'keyboard_moved_down' => __( 'Moved down.', 'advanced-post-order' ),
					'keyboard_saved'      => __( 'Position saved.', 'advanced-post-order' ),
					'keyboard_cancelled'  => __( 'Reorder cancelled.', 'advanced-post-order' ),
				],
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
	 * Add ordering mode indicator to admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function admin_bar_indicator( $wp_admin_bar ) {
		$mode = self::get_mode();
		if ( ! $mode ) {
			return;
		}

		$label = $mode === 'term'
			? __( 'APO: Per-Term Mode', 'advanced-post-order' )
			: __( 'APO: Global Mode', 'advanced-post-order' );

		$wp_admin_bar->add_node( [
			'id'    => 'apo-mode',
			'title' => $label,
			'href'  => admin_url( 'options-general.php?page=advanced-post-order' ),
			'meta'  => [ 'class' => 'apo-admin-bar-mode' ],
		] );
	}

	/**
	 * Add order position "#" column to post list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_order_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $label ) {
			if ( $key === 'cb' ) {
				$new_columns[ $key ] = $label;
				$new_columns['apo_order'] = '#';
				continue;
			}
			$new_columns[ $key ] = $label;
		}
		return $new_columns;
	}

	/**
	 * Render order position column value.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_order_column( $column, $post_id ) {
		if ( $column === 'apo_order' ) {
			$post = get_post( $post_id );
			if ( $post && ! is_wp_error( $post ) ) {
				echo (int) $post->menu_order;
			}
		}
	}
}

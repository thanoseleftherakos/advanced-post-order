<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Polylang compatibility for Advanced Post Order.
 *
 * Maps term IDs and filters post IDs to the current language
 * so per-term ordering works correctly in multilingual sites.
 */
class APO_Compat_Polylang {

	public static function init() {
		add_filter( 'apo_get_term_post_order', [ __CLASS__, 'filter_term_post_order' ], 10, 2 );
	}

	/**
	 * Filter per-term post order for Polylang compatibility.
	 *
	 * Maps term IDs via pll_get_term() and filters post IDs
	 * to only include those in the current language.
	 *
	 * @param array $ordered_ids Array of post IDs in order.
	 * @param int   $term_id     The term ID.
	 * @return array Filtered array of post IDs.
	 */
	public static function filter_term_post_order( $ordered_ids, $term_id ) {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return $ordered_ids;
		}

		$current_language = pll_current_language();
		$default_language = pll_default_language();

		// If we're on the default language, just filter post IDs.
		if ( $current_language === $default_language ) {
			return self::filter_posts_by_language( $ordered_ids, $current_language );
		}

		// Map term to default language to get the canonical saved order.
		if ( function_exists( 'pll_get_term' ) ) {
			$default_term_id = pll_get_term( $term_id, $default_language );

			if ( $default_term_id && $default_term_id !== $term_id ) {
				$default_order = APO_Core::get_term_order( $default_term_id );
				if ( ! empty( $default_order ) && empty( $ordered_ids ) ) {
					$ordered_ids = $default_order;
				}
			}
		}

		// Map post IDs to current language equivalents.
		if ( function_exists( 'pll_get_post' ) ) {
			$mapped_ids = [];
			foreach ( $ordered_ids as $post_id ) {
				$translated_id = pll_get_post( $post_id, $current_language );
				if ( $translated_id ) {
					$mapped_ids[] = (int) $translated_id;
				}
			}
			return $mapped_ids;
		}

		return self::filter_posts_by_language( $ordered_ids, $current_language );
	}

	/**
	 * Filter post IDs to only include those in the specified language.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param string $language Language slug.
	 * @return array Filtered post IDs.
	 */
	private static function filter_posts_by_language( $post_ids, $language ) {
		if ( empty( $post_ids ) || ! $language || ! function_exists( 'pll_get_post_language' ) ) {
			return $post_ids;
		}

		$filtered = [];
		foreach ( $post_ids as $post_id ) {
			$post_lang = pll_get_post_language( $post_id );
			if ( $post_lang === $language ) {
				$filtered[] = $post_id;
			}
		}

		return $filtered;
	}
}

<?php
/**
 * Gutenberg Block implementation.
 *
 * @package samePosts.
 *
 * @since 4.9
 */

namespace samePosts;

require_once __DIR__ . '/includes/block-attributes.php';

/**
 * Markup wrapping the title in the block.
 *
 * The classic widget gets before_title / after_title from the sidebar it sits
 * in. A block has no such args, so it brings its own wrapper.
 */
const BLOCK_BEFORE_TITLE = '<h2 class="widget-title">';
const BLOCK_AFTER_TITLE  = '</h2>';

/**
 * Renders the `tiptip/same-posts-block` on the server.
 *
 * Uses the same render core as the classic widget, so both produce the same
 * list. The attributes are translated into a widget instance first -- see
 * includes/block-attributes.php for why that step cannot be skipped.
 *
 * @param array     $attributes The block attributes.
 * @param string    $content    The block's inner content, unused.
 * @param \WP_Block $block      The block instance, carries the postId context.
 *
 * @return string The rendered block content.
 */
function render_same_posts_block( $attributes, $content = '', $block = null ) {
	$current_post_id = current_post_id( $block );

	// Nothing to relate to: no post, and not a term archive either.
	if ( ! $current_post_id && ! is_archive() ) {
		return '';
	}

	$widget   = new Widget();
	$instance = block_attributes_to_instance( $attributes );

	// In the editor's preview the terms picked in the sidebar stand in for the
	// saved ones, until render_html() is done.
	$preview_terms = preview_terms( $current_post_id );
	$use_preview   = function ( $terms, $post_id, $taxonomy ) use ( $preview_terms, $current_post_id ) {
		if ( (int) $post_id !== $current_post_id || ! array_key_exists( $taxonomy, $preview_terms ) ) {
			return $terms;
		}
		return $preview_terms[ $taxonomy ];
	};

	if ( $preview_terms ) {
		add_filter( 'get_the_terms', $use_preview, 10, 3 );
	}

	$html = $widget->render_html( $instance, $current_post_id, BLOCK_BEFORE_TITLE, BLOCK_AFTER_TITLE );

	if ( $preview_terms ) {
		remove_filter( 'get_the_terms', $use_preview, 10 );
	}

	if ( '' === $html ) {
		return '';
	}

	return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $html );
}

/**
 * Determines the post the block relates to.
 *
 * Three contexts have to be served:
 *
 * - a term archive, where the queried term rather than a post is the basis of
 *   the query -- 0 tells the render core to use the queried object;
 * - a single post or a post template, where the postId context is set;
 * - the editor's preview, which arrives through the block-renderer REST route
 *   without any query context. WordPress sets up the post from the post_id
 *   that ServerSideRender sends along, so get_the_ID() answers there too.
 *
 * @param \WP_Block|null $block The block instance.
 *
 * @return int The post id, or 0 when there is none.
 */
function current_post_id( $block ) {
	if ( is_archive() ) {
		return 0;
	}

	if ( $block instanceof \WP_Block && isset( $block->context['postId'] ) ) {
		return (int) $block->context['postId'];
	}

	return (int) get_the_ID();
}

/**
 * The not yet saved terms of the post being edited, for the editor's preview.
 *
 * The preview is rendered through the block-renderer REST route, which knows
 * only the terms stored in the database. A new post has none there, so the
 * block would stay empty until the post is saved, and a changed category
 * would show up only after saving. The editor therefore sends the terms that
 * are currently picked in the sidebar as `samePostsPreviewTerms`, one entry
 * per taxonomy with a comma separated list of term ids ("0" for none). See
 * src/edit.js.
 *
 * An empty category selection stands for the default category where saving
 * would assign it, see requires_default_category().
 *
 * Only honoured in a REST request by a user who may edit the post, and only
 * for taxonomies of that post.
 *
 * @param int $post_id The post the block relates to.
 *
 * @return array<string, \WP_Term[]|false> Terms per taxonomy, in the shape
 *                                         get_the_terms() returns them. Empty
 *                                         when there is no preview.
 */
function preview_terms( $post_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, REST cookie auth checks the nonce.
	if ( ! $post_id || ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || ! isset( $_GET['samePostsPreviewTerms'] ) ) {
		return array();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
	$requested = wp_unslash( $_GET['samePostsPreviewTerms'] );

	if ( ! is_array( $requested ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return array();
	}

	$taxonomies = get_object_taxonomies( get_post( $post_id ) );
	$preview    = array();

	foreach ( $requested as $taxonomy => $term_ids ) {
		if ( ! is_string( $term_ids ) || ! in_array( $taxonomy, $taxonomies, true ) ) {
			continue;
		}

		$term_ids = array_filter( array_map( 'absint', explode( ',', $term_ids ) ) );

		if ( ! $term_ids && 'category' === $taxonomy && requires_default_category( $post_id ) ) {
			$term_ids = array( (int) get_option( 'default_category' ) );
		}

		$terms = $term_ids ? get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'include'    => $term_ids,
				'hide_empty' => false,
			)
		) : array();

		// get_the_terms() reports "no terms" as false, not as an empty array.
		$preview[ $taxonomy ] = ( $terms && ! is_wp_error( $terms ) ) ? $terms : false;
	}

	return $preview;
}

/**
 * Whether saving the post without a category gives it the default category.
 *
 * Mirrors wp_set_post_categories(): 'post' and the post types added through
 * the `default_category_post_types` filter get the `default_category` option
 * when no category is picked. The preview follows suit, or it would show an
 * empty block for a post that lists posts once it is saved. Unlike WordPress
 * the auto-draft status is not exempt here: saving from the editor ends it.
 *
 * @param int $post_id The post being edited.
 *
 * @return bool
 */
function requires_default_category( $post_id ) {
	$post_type = get_post_type( $post_id );

	/** This filter is documented in wp-includes/post.php */
	$post_types = apply_filters( 'default_category_post_types', array() );
	$post_types = array_merge( $post_types, array( 'post' ) );

	return in_array( $post_type, $post_types, true ) && is_object_in_taxonomy( $post_type, 'category' );
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/writing-your-first-block-type/
 */
function same_posts_block_init() {
	register_block_type(
		__DIR__,
		array(
			'render_callback' => __NAMESPACE__ . '\render_same_posts_block',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\same_posts_block_init' );

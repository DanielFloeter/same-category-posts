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

	$html = $widget->render_html( $instance, $current_post_id, BLOCK_BEFORE_TITLE, BLOCK_AFTER_TITLE );

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

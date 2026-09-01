<?php
/**
 * Gutenberg Block implementation.
 *
 * @package samePosts.
 *
 * @since 4.9
 */

namespace samePosts;

/**
 * Renders the `tiptip/same-posts-block` on the server.
 *
 * Interim implementation: it still calls Widget::itemHTML() for the single
 * current post, so the block shows one entry rather than a list. Phase 1 of
 * plan.md replaces this with the shared render core extracted from
 * Widget::widget() and the attribute mapping in includes/block-attributes.php.
 *
 * @param array $attributes The block attributes.
 *
 * @return string The rendered block content.
 */
function render_same_posts_block( $attributes ) {
	$widget   = new Widget();
	$instance = array();

	// The widget reads its options with isset(), so an option that is off must
	// be absent from the instance rather than present and false. See the header
	// of includes/block-attributes.php.
	if ( isset( $attributes['order'] ) && 'desc' !== $attributes['order'] ) {
		$instance['asc_sort_order'] = true;
	}

	if ( isset( $attributes['title'] ) ) {
		$instance['title'] = $attributes['title'];
	}

	$current_post_id = '';
	if ( is_singular() ) {
		$current_post_id = get_the_ID();
	}

	return $widget->itemHTML( $instance, $current_post_id );
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

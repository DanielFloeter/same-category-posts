<?php
/**
 * REST route that feeds the block editor's taxonomy and term filters.
 *
 * The classic widget's form builds its "Filter" section on the server: it
 * walks the public taxonomies, groups them by post type and lists the terms of
 * each. The block editor needs the same data, and it needs it grouped the same
 * way, or the two forms would offer different choices.
 *
 * Rather than rebuild that grouping in JavaScript from several core routes,
 * this route hands over the finished structure and reuses
 * Widget::initPostTypesAndTaxes() for the taxonomy-to-post-type mapping and
 * for the default taxonomy per post type -- the same method the widget uses.
 *
 * @package samePosts
 *
 * @since 1.3.0
 */

namespace samePosts;

const REST_NAMESPACE = 'same-posts/v1';

/**
 * Registers the route.
 */
function register_rest_routes() {
	register_rest_route(
		REST_NAMESPACE,
		'/taxonomies',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\rest_taxonomies',
			'permission_callback' => __NAMESPACE__ . '\rest_can_edit_posts',
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );

/**
 * The route only serves the editor, so it needs the editor's capability.
 *
 * @return bool
 */
function rest_can_edit_posts() {
	return current_user_can( 'edit_posts' );
}

/**
 * Builds the post type => taxonomies => terms structure.
 *
 * Mirrors Widget::form(): only public taxonomies, only those that have terms,
 * terms including empty ones, grouped under the post type that
 * initPostTypesAndTaxes() associated with the taxonomy.
 *
 * @return array<int, array<string, mixed>> One entry per post type that has
 *                                          at least one taxonomy with terms.
 */
function taxonomy_choices() {
	$widget   = new Widget();
	$instance = array();

	// Fills $instance['post_types'] (taxonomy => post type + hierarchical) and
	// $instance['include_tax'] (post type => default taxonomy).
	$widget->initPostTypesAndTaxes( $instance );

	$post_types  = isset( $instance['post_types'] ) ? $instance['post_types'] : array();
	$include_tax = isset( $instance['include_tax'] ) ? $instance['include_tax'] : array();

	$grouped = array();

	foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
		if ( ! isset( $post_types[ $taxonomy->name ]['post_type'] ) ) {
			continue;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy->name,
				'hide_empty' => false, // Show not yet populated terms as well.
				'fields'     => 'id=>name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue; // The widget's form skips taxonomies without terms.
		}

		$post_type = $post_types[ $taxonomy->name ]['post_type'];

		$term_list = array();
		foreach ( $terms as $term_id => $term_name ) {
			$term_list[] = array(
				'id'   => (int) $term_id,
				'name' => $term_name,
			);
		}

		if ( ! isset( $grouped[ $post_type ] ) ) {
			$post_type_object = get_post_type_object( $post_type );

			$grouped[ $post_type ] = array(
				'postType'        => $post_type,
				'label'           => $post_type_object ? $post_type_object->label : $post_type,
				'defaultTaxonomy' => isset( $include_tax[ $post_type ] ) ? $include_tax[ $post_type ] : '',
				'taxonomies'      => array(),
			);
		}

		$grouped[ $post_type ]['taxonomies'][] = array(
			'name'         => $taxonomy->name,
			'label'        => $taxonomy->labels->name,
			'hierarchical' => (bool) $taxonomy->hierarchical,
			'terms'        => $term_list,
		);
	}

	return array_values( $grouped );
}

/**
 * Route callback.
 *
 * @return \WP_REST_Response
 */
function rest_taxonomies() {
	return rest_ensure_response( taxonomy_choices() );
}

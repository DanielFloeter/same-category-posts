<?php
/**
 * Translation of Gutenberg block attributes into the option array ("instance")
 * that the classic widget consumes.
 *
 * This file is deliberately free of any WordPress dependency so that it can be
 * unit tested without bootstrapping WordPress.
 *
 * Why a dedicated translation step exists at all:
 *
 * The widget stores its options as a form submission. Unchecked checkboxes are
 * simply absent from that array, and the rendering code accordingly asks
 * `isset( $instance['excerpt'] )` rather than testing the value. A block
 * attribute declared as `"type": "boolean"` is by contrast *always* present --
 * an unchecked toggle arrives as `false`, which `isset()` reports as set.
 * Passing block attributes straight through would therefore turn every switched
 * off option back on.
 *
 * The rule that follows: a value that means "off", "empty" or "unset" is
 * omitted from the resulting instance, never passed along as false or "".
 *
 * @package samePosts
 * @since   1.2.1
 */

namespace samePosts;

/**
 * Accepted values of the widget's "Sort by" option.
 *
 * Mirrors $valid_sort_orders in Widget::widget().
 */
const SORT_BY_VALUES = array( 'date', 'title', 'comment_count', 'rand' );

/**
 * Maps block attribute names to widget instance keys.
 *
 * Each entry is array( instance key, value type ). The instance keys follow the
 * widget's historical naming, which is snake_case with the sole exception of
 * `thumbTop` -- that inconsistency is intentional and must not be "fixed" here,
 * because Widget::itemHTML() reads exactly that key.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function attribute_map() {
	return array(
		// Title.
		'hideTitle'          => array( 'hide_title', 'bool' ),
		'title'              => array( 'title', 'string' ),
		'titleLink'          => array( 'title_link', 'bool' ),

		// Filter.
		'sortBy'             => array( 'sort_by', 'sort_by' ),
		'ascSortOrder'       => array( 'asc_sort_order', 'bool' ),
		'num'                => array( 'num', 'int' ),
		'separateCategories' => array( 'separate_categories', 'bool' ),
		'numPerCate'         => array( 'num_per_cate', 'int' ),
		'excludeCurrentPost' => array( 'exclude_current_post', 'bool' ),
		'excludeStickyPosts' => array( 'exclude_sticky_posts', 'bool' ),
		'excludeChildren'    => array( 'exclude_children', 'bool' ),
		'excludeNoChildren'  => array( 'exclude_no_children', 'bool' ),
		'includeTax'         => array( 'include_tax', 'string_map' ),
		'excludeTerms'       => array( 'exclude_terms', 'term_map' ),

		// Thumbnails.
		'thumb'              => array( 'thumb', 'bool' ),
		'thumbTop'           => array( 'thumbTop', 'bool' ),
		'thumbW'             => array( 'thumb_w', 'int' ),
		'thumbH'             => array( 'thumb_h', 'int' ),
		'useCssCropping'     => array( 'use_css_cropping', 'bool' ),

		// Post details.
		'excerpt'            => array( 'excerpt', 'bool' ),
		'excerptLength'      => array( 'excerpt_length', 'int' ),
		'excerptMoreText'    => array( 'excerpt_more_text', 'string' ),
		'commentNum'         => array( 'comment_num', 'bool' ),
		'date'               => array( 'date', 'bool' ),
		'useWpDateFormat'    => array( 'use_wp_date_format', 'bool' ),
		'dateFormat'         => array( 'date_format', 'string' ),
		'dateLink'           => array( 'date_link', 'bool' ),
		'author'             => array( 'author', 'bool' ),
	);
}

/**
 * Converts block attributes into a widget instance array.
 *
 * Attributes that are unknown, switched off or empty do not appear in the
 * result at all -- see the file header for why that matters.
 *
 * @param  mixed $attributes The block attributes as handed to the render callback.
 * @return array             An instance array as Widget::widget() expects it.
 */
function block_attributes_to_instance( $attributes ) {
	$instance = array();

	if ( ! is_array( $attributes ) ) {
		return $instance;
	}

	foreach ( attribute_map() as $attribute => $spec ) {
		if ( ! array_key_exists( $attribute, $attributes ) ) {
			continue;
		}

		list( $key, $type ) = $spec;

		$value = normalize_attribute( $attributes[ $attribute ], $type );

		if ( null === $value ) {
			continue;
		}

		$instance[ $key ] = $value;
	}

	return $instance;
}

/**
 * Normalizes a single attribute value.
 *
 * @param  mixed  $value The raw attribute value.
 * @param  string $type  One of bool, string, int, sort_by, string_map, term_map.
 * @return mixed         The normalized value, or null when the option is to be omitted.
 */
function normalize_attribute( $value, $type ) {
	switch ( $type ) {
		case 'bool':
			return $value ? true : null;

		case 'string':
			if ( ! is_scalar( $value ) ) {
				return null;
			}
			$value = trim( (string) $value );
			return '' === $value ? null : $value;

		case 'int':
			if ( ! is_scalar( $value ) || '' === $value || is_bool( $value ) ) {
				return null;
			}
			$value = (int) $value;
			return $value > 0 ? $value : null;

		case 'sort_by':
			if ( ! is_scalar( $value ) ) {
				return null;
			}
			$value = (string) $value;
			return in_array( $value, SORT_BY_VALUES, true ) ? $value : null;

		case 'string_map':
			return normalize_string_map( $value );

		case 'term_map':
			return normalize_term_map( $value );
	}

	return null;
}

/**
 * Normalizes include_tax: post type => taxonomy name.
 *
 * @param  mixed $value Raw value.
 * @return array|null   Sanitized map, or null when nothing usable remains.
 */
function normalize_string_map( $value ) {
	if ( ! is_array( $value ) ) {
		return null;
	}

	$map = array();

	foreach ( $value as $post_type => $taxonomy ) {
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			continue;
		}
		if ( ! is_scalar( $taxonomy ) || is_bool( $taxonomy ) ) {
			continue;
		}
		$taxonomy = trim( (string) $taxonomy );
		if ( '' === $taxonomy ) {
			continue;
		}
		$map[ $post_type ] = $taxonomy;
	}

	return empty( $map ) ? null : $map;
}

/**
 * Normalizes exclude_terms: taxonomy name => list of term ids.
 *
 * @param  mixed $value Raw value.
 * @return array|null   Sanitized map, or null when nothing usable remains.
 */
function normalize_term_map( $value ) {
	if ( ! is_array( $value ) ) {
		return null;
	}

	$map = array();

	foreach ( $value as $taxonomy => $term_ids ) {
		if ( ! is_string( $taxonomy ) || '' === $taxonomy || ! is_array( $term_ids ) ) {
			continue;
		}

		$ids = array();

		foreach ( $term_ids as $term_id ) {
			if ( ! is_scalar( $term_id ) || is_bool( $term_id ) ) {
				continue;
			}
			$term_id = (int) $term_id;
			if ( $term_id > 0 ) {
				$ids[] = $term_id;
			}
		}

		if ( ! empty( $ids ) ) {
			$map[ $taxonomy ] = array_values( array_unique( $ids ) );
		}
	}

	return empty( $map ) ? null : $map;
}

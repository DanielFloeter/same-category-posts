<?php
/**
 * Unit tests for the block attribute to widget instance translation.
 *
 * @package samePosts
 */

use PHPUnit\Framework\TestCase;

use function samePosts\attribute_map;
use function samePosts\block_attributes_to_instance;

class BlockAttributesTest extends TestCase {

	/**
	 * Collects every attribute of a given type from the map.
	 *
	 * @param  string $type Value type as declared in attribute_map().
	 * @return array<string, string> Attribute name => instance key.
	 */
	private function attributes_of_type( $type ) {
		$found = array();

		foreach ( attribute_map() as $attribute => $spec ) {
			if ( $spec[1] === $type ) {
				$found[ $attribute ] = $spec[0];
			}
		}

		return $found;
	}

	public function test_empty_attributes_produce_empty_instance() {
		$this->assertSame( array(), block_attributes_to_instance( array() ) );
	}

	public function test_non_array_input_produces_empty_instance() {
		$this->assertSame( array(), block_attributes_to_instance( null ) );
		$this->assertSame( array(), block_attributes_to_instance( 'title' ) );
	}

	public function test_unknown_attributes_are_dropped() {
		$instance = block_attributes_to_instance(
			array(
				'title'          => 'Related',
				'evilAttribute'  => 'payload',
				'groupBy'        => 'monthly',
				'className'      => 'is-style-default',
			)
		);

		$this->assertSame( array( 'title' => 'Related' ), $instance );
	}

	/**
	 * The central regression guard.
	 *
	 * Widget::itemHTML() and Widget::show_thumb() ask isset( $instance[...] ),
	 * not whether the value is truthy. A boolean block attribute is always
	 * present, so a switched off toggle arriving as false would read as "on".
	 * Every boolean that is false must therefore be absent from the instance.
	 */
	public function test_false_booleans_are_omitted_so_isset_reports_them_as_off() {
		$attributes = array();
		$keys       = $this->attributes_of_type( 'bool' );

		foreach ( array_keys( $keys ) as $attribute ) {
			$attributes[ $attribute ] = false;
		}

		$instance = block_attributes_to_instance( $attributes );

		$this->assertSame( array(), $instance, 'No false boolean may survive into the instance.' );

		foreach ( $keys as $attribute => $instance_key ) {
			$this->assertFalse(
				isset( $instance[ $instance_key ] ),
				sprintf( 'isset() must report "%s" as off when "%s" is false.', $instance_key, $attribute )
			);
		}
	}

	public function test_true_booleans_are_kept() {
		$keys = $this->attributes_of_type( 'bool' );

		foreach ( $keys as $attribute => $instance_key ) {
			$instance = block_attributes_to_instance( array( $attribute => true ) );

			$this->assertTrue(
				isset( $instance[ $instance_key ] ),
				sprintf( '"%s" must be set when "%s" is true.', $instance_key, $attribute )
			);
			$this->assertTrue( $instance[ $instance_key ] );
		}
	}

	/**
	 * thumbTop is the one instance key that is not snake_case. Widget::itemHTML()
	 * reads $instance["thumbTop"], so renaming it here would silently disable
	 * the "Thumbnail to top" option.
	 */
	public function test_thumb_top_keeps_its_historical_camel_case_key() {
		$instance = block_attributes_to_instance( array( 'thumbTop' => true ) );

		$this->assertArrayHasKey( 'thumbTop', $instance );
		$this->assertArrayNotHasKey( 'thumb_top', $instance );
	}

	public function test_title_is_trimmed() {
		$instance = block_attributes_to_instance( array( 'title' => '  Same category  ' ) );

		$this->assertSame( 'Same category', $instance['title'] );
	}

	public function test_empty_title_is_omitted() {
		$this->assertSame( array(), block_attributes_to_instance( array( 'title' => '' ) ) );
		$this->assertSame( array(), block_attributes_to_instance( array( 'title' => '   ' ) ) );
	}

	public function test_title_placeholders_are_passed_through_untouched() {
		$instance = block_attributes_to_instance( array( 'title' => 'More in %cat-all%' ) );

		$this->assertSame( 'More in %cat-all%', $instance['title'] );
	}

	/**
	 * Widget::widget() reads num as "0 or absent means no limit". Omitting a
	 * zero keeps that behaviour and avoids a meaningless posts_per_page => 0.
	 */
	public function test_numeric_options_drop_zero_and_negative_values() {
		foreach ( array( 'num', 'numPerCate', 'excerptLength', 'thumbW', 'thumbH' ) as $attribute ) {
			$this->assertSame(
				array(),
				block_attributes_to_instance( array( $attribute => 0 ) ),
				sprintf( '"%s" = 0 must be omitted.', $attribute )
			);
			$this->assertSame(
				array(),
				block_attributes_to_instance( array( $attribute => -5 ) ),
				sprintf( '"%s" = -5 must be omitted.', $attribute )
			);
		}
	}

	public function test_numeric_options_are_cast_to_int() {
		$instance = block_attributes_to_instance(
			array(
				'num'    => '7',
				'thumbW' => 150.0,
			)
		);

		$this->assertSame( 7, $instance['num'] );
		$this->assertSame( 150, $instance['thumb_w'] );
	}

	public function test_numeric_options_reject_booleans() {
		$this->assertSame( array(), block_attributes_to_instance( array( 'num' => true ) ) );
	}

	public function test_sort_by_accepts_only_the_widgets_valid_values() {
		foreach ( array( 'date', 'title', 'comment_count', 'rand' ) as $value ) {
			$instance = block_attributes_to_instance( array( 'sortBy' => $value ) );
			$this->assertSame( $value, $instance['sort_by'] );
		}
	}

	public function test_sort_by_drops_unknown_values() {
		$this->assertSame( array(), block_attributes_to_instance( array( 'sortBy' => 'post_date' ) ) );
		$this->assertSame( array(), block_attributes_to_instance( array( 'sortBy' => '' ) ) );
	}

	public function test_include_tax_maps_post_types_to_taxonomies() {
		$instance = block_attributes_to_instance(
			array(
				'includeTax' => array(
					'post'    => 'category',
					'product' => 'product_cat',
				),
			)
		);

		$this->assertSame(
			array(
				'post'    => 'category',
				'product' => 'product_cat',
			),
			$instance['include_tax']
		);
	}

	public function test_include_tax_drops_empty_entries() {
		$instance = block_attributes_to_instance(
			array(
				'includeTax' => array(
					'post'    => 'category',
					'page'    => '',
					'product' => null,
				),
			)
		);

		$this->assertSame( array( 'post' => 'category' ), $instance['include_tax'] );
	}

	public function test_include_tax_is_omitted_when_nothing_usable_remains() {
		$this->assertSame( array(), block_attributes_to_instance( array( 'includeTax' => array() ) ) );
		$this->assertSame( array(), block_attributes_to_instance( array( 'includeTax' => 'category' ) ) );
	}

	public function test_exclude_terms_are_cast_to_positive_ints() {
		$instance = block_attributes_to_instance(
			array(
				'excludeTerms' => array(
					'category' => array( '3', 7, 0, -1, '12' ),
				),
			)
		);

		$this->assertSame( array( 3, 7, 12 ), $instance['exclude_terms']['category'] );
	}

	public function test_exclude_terms_deduplicates() {
		$instance = block_attributes_to_instance(
			array(
				'excludeTerms' => array(
					'category' => array( 4, '4', 9 ),
				),
			)
		);

		$this->assertSame( array( 4, 9 ), $instance['exclude_terms']['category'] );
	}

	public function test_exclude_terms_drops_taxonomies_without_usable_ids() {
		$instance = block_attributes_to_instance(
			array(
				'excludeTerms' => array(
					'category' => array( 5 ),
					'post_tag' => array(),
					'genre'    => array( 0, -2 ),
				),
			)
		);

		$this->assertSame( array( 'category' => array( 5 ) ), $instance['exclude_terms'] );
	}

	/**
	 * A full inspector configuration, checked as a whole so that a change to
	 * the map shows up as one readable diff.
	 */
	public function test_a_fully_configured_block_maps_to_the_expected_instance() {
		$instance = block_attributes_to_instance(
			array(
				'hideTitle'          => false,
				'title'              => 'More in %cat%',
				'titleLink'          => true,
				'sortBy'             => 'title',
				'ascSortOrder'       => true,
				'num'                => 5,
				'separateCategories' => false,
				'numPerCate'         => 0,
				'excludeCurrentPost' => true,
				'excludeStickyPosts' => false,
				'excludeChildren'    => false,
				'excludeNoChildren'  => false,
				'includeTax'         => array( 'post' => 'category' ),
				'excludeTerms'       => array( 'category' => array( 12 ) ),
				'thumb'              => true,
				'thumbTop'           => true,
				'thumbW'             => 120,
				'thumbH'             => 80,
				'useCssCropping'     => true,
				'excerpt'            => true,
				'excerptLength'      => 30,
				'excerptMoreText'    => '... weiterlesen',
				'commentNum'         => false,
				'date'               => true,
				'useWpDateFormat'    => false,
				'dateFormat'         => 'd.m.Y',
				'dateLink'           => true,
				'author'             => false,
				'disableThemeStyles' => true,
			)
		);

		$this->assertSame(
			array(
				'title'                => 'More in %cat%',
				'title_link'           => true,
				'sort_by'              => 'title',
				'asc_sort_order'       => true,
				'num'                  => 5,
				'exclude_current_post' => true,
				'include_tax'          => array( 'post' => 'category' ),
				'exclude_terms'        => array( 'category' => array( 12 ) ),
				'thumb'                => true,
				'thumbTop'             => true,
				'thumb_w'              => 120,
				'thumb_h'              => 80,
				'use_css_cropping'     => true,
				'excerpt'              => true,
				'excerpt_length'       => 30,
				'excerpt_more_text'    => '... weiterlesen',
				'date'                 => true,
				'date_format'          => 'd.m.Y',
				'date_link'            => true,
			),
			$instance
		);
	}

	/**
	 * The General panel controls stylesheet loading, not the query or the
	 * markup of a single item, so those attributes have no instance counterpart.
	 */
	public function test_style_attributes_do_not_leak_into_the_instance() {
		$instance = block_attributes_to_instance(
			array(
				'disableCSS'         => true,
				'disableFontStyles'  => true,
				'disableThemeStyles' => true,
			)
		);

		$this->assertSame( array(), $instance );
	}

	public function test_every_mapped_attribute_has_a_unique_instance_key() {
		$keys = array();

		foreach ( attribute_map() as $attribute => $spec ) {
			$this->assertArrayNotHasKey(
				$spec[0],
				$keys,
				sprintf( 'Instance key "%s" is claimed by both "%s" and "%s".', $spec[0], $keys[ $spec[0] ] ?? '', $attribute )
			);
			$keys[ $spec[0] ] = $attribute;
		}

		$this->assertNotEmpty( $keys );
	}
}

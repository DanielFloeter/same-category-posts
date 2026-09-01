<?php
/**
 * Unit tests for the thumbnail cropping arithmetic.
 *
 * The function decides how an image has to be inflated so that it fills the
 * requested thumbnail box, and by how much it then has to be pulled out of
 * that box to stay centred. Which of the two axes gets cropped depends on
 * which aspect ratio is wider.
 *
 * @package samePosts
 */

use PHPUnit\Framework\TestCase;

use function samePosts\same_category_posts_get_image_size;

class ImageSizeTest extends TestCase {

	public function test_returns_all_four_keys() {
		$size = same_category_posts_get_image_size( 100, 80, 200, 100 );

		$this->assertArrayHasKey( 'image_w', $size );
		$this->assertArrayHasKey( 'image_h', $size );
		$this->assertArrayHasKey( 'marginAttr', $size );
		$this->assertArrayHasKey( 'marginVal', $size );
	}

	/**
	 * Image wider than the box: the height is filled and the sides are cropped.
	 *
	 * Box 100x80 (1.25), image 200x100 (2.0). Scaling the image to 80 high
	 * makes it 160 wide, so 60px too wide -- 30 on each side.
	 */
	public function test_wider_image_is_cropped_left_and_right() {
		$size = same_category_posts_get_image_size( 100, 80, 200, 100 );

		$this->assertSame( 80, $size['image_h'] );
		$this->assertSame( 160.0, $size['image_w'] );
		$this->assertSame( 'margin-left', $size['marginAttr'] );
		$this->assertSame( 30.0, $size['marginVal'] );
	}

	/**
	 * Image taller than the box: the width is filled and top and bottom go.
	 *
	 * Box 100x80 (1.25), image 100x200 (0.5). Scaling the image to 100 wide
	 * leaves it 200 high, so 120px too tall -- 60 above and below.
	 *
	 * Height and margin come out as ints here rather than floats: PHP's
	 * division operator returns an int when both operands are ints and divide
	 * evenly, and both 100 / 100 and 120 / 2 do.
	 */
	public function test_taller_image_is_cropped_top_and_bottom() {
		$size = same_category_posts_get_image_size( 100, 80, 100, 200 );

		$this->assertSame( 100, $size['image_w'] );
		$this->assertSame( 200, $size['image_h'] );
		$this->assertSame( 'margin-top', $size['marginAttr'] );
		$this->assertSame( 60, $size['marginVal'] );
	}

	/**
	 * Same aspect ratio: nothing has to be cropped, so there is no margin.
	 *
	 * The equal case takes the else branch, which is why the axis is reported
	 * as margin-top rather than margin-left.
	 */
	public function test_same_ratio_needs_no_margin() {
		$size = same_category_posts_get_image_size( 100, 50, 200, 100 );

		$this->assertSame( 100, $size['image_w'] );
		$this->assertSame( 50.0, $size['image_h'] );
		$this->assertSame( 'margin-top', $size['marginAttr'] );
		$this->assertSame( 0.0, $size['marginVal'] );
	}

	/**
	 * A square box in front of a wide image.
	 *
	 * Box 150x150, image 300x150: scaled to 150 high the image stays 300
	 * wide, so 75px hang over on each side.
	 */
	public function test_square_box_with_wide_image() {
		$size = same_category_posts_get_image_size( 150, 150, 300, 150 );

		$this->assertSame( 150, $size['image_h'] );
		$this->assertSame( 300, $size['image_w'] );
		$this->assertSame( 'margin-left', $size['marginAttr'] );
		$this->assertSame( 75, $size['marginVal'] );
	}

	/**
	 * Half pixels are not rounded away; the caller writes them into the style
	 * attribute as they are.
	 */
	public function test_fractional_margins_are_kept() {
		$size = same_category_posts_get_image_size( 3, 2, 5, 5 );

		$this->assertSame( 3, $size['image_w'] );
		$this->assertSame( 3.0, $size['image_h'] );
		$this->assertSame( 'margin-top', $size['marginAttr'] );
		$this->assertSame( 0.5, $size['marginVal'] );
	}

	/**
	 * The box dimensions arrive from the widget form as strings.
	 */
	public function test_string_dimensions_are_calculated_the_same_way() {
		$from_strings = same_category_posts_get_image_size( '100', '80', 200, 100 );
		$from_ints    = same_category_posts_get_image_size( 100, 80, 200, 100 );

		$this->assertSame( $from_ints['image_w'], $from_strings['image_w'] );
		$this->assertSame( $from_ints['marginAttr'], $from_strings['marginAttr'] );
		$this->assertSame( $from_ints['marginVal'], $from_strings['marginVal'] );
	}
}

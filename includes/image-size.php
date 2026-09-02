<?php
/**
 * Aspect ratio arithmetic for the CSS cropping of thumbnails.
 *
 * Free of any WordPress dependency so that it can be unit tested without
 * bootstrapping WordPress; see plan.md, section 5.
 *
 * @package samePosts
 * @since   1.2.0
 */

namespace samePosts;

/**
 * Get image size
 *
 * $thumb_w, $thumb_h - the width and height of the thumbnail in the widget settings
 * $image_w,$image_h - the width and height of the actual image being displayed
 *
 * return: an array with the width and height of the element containing the image
 *
 * All four values have to be non-zero: the function divides by $thumb_h,
 * $image_h and $image_w. The only caller, Widget::post_thumbnail_html(),
 * checks the thumbnail dimensions beforehand and takes the image dimensions
 * from getimagesize().
 */
function same_category_posts_get_image_size( $thumb_w,$thumb_h,$image_w,$image_h) {
	
	$image_size = array('image_h' => $thumb_h, 'image_w' => $thumb_w, 'marginAttr' => '', 'marginVal' => '');
	$relation_thumbnail = $thumb_w / $thumb_h;
	$relation_cropped = $image_w / $image_h;
	
	if ($relation_thumbnail < $relation_cropped) {
		// crop left and right site
		// thumbnail width/height ration is smaller, need to inflate the height of the image to thumb height
		// and adjust width to keep aspect ration of image
		$image_size['image_h'] = $thumb_h;
		$image_size['image_w'] = $thumb_h / $image_h * $image_w; 
		$image_size['marginAttr'] = 'margin-left';
		$image_size['marginVal'] = ($image_size['image_w'] - $thumb_w) / 2;
	} else {
		// crop top and bottom
		// thumbnail width/height ration is bigger, need to inflate the width of the image to thumb width
		// and adjust height to keep aspect ration of image
		$image_size['image_w'] = $thumb_w;
		$image_size['image_h'] = $thumb_w / $image_w * $image_h; 
		$image_size['marginAttr'] = 'margin-top';
		$image_size['marginVal'] = ($image_size['image_h'] - $thumb_h) / 2;
	}
	
	return $image_size;
}

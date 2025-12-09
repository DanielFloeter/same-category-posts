<?php
/*
Plugin Name: Same Category Posts
Plugin URI: https://wordpress.org/plugins/same-category-posts/
Description: Adds a widget that shows the most recent posts from a single category.
Author: Daniel Floeter
Version: 1.2.0
Author URI: https://profiles.wordpress.org/kometschuh/
*/

namespace samePosts;

// Don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

define( 'SAME_CATEGORY_POSTS_VERSION', "1.2.0");

require_once __DIR__ . '/same-posts-block.php';

/**
 * Register our styles
 *
 * @return void
 */
function same_category_posts_styles() {
	wp_register_style( 'same-category-posts', plugins_url( 'same-category-posts/same-category-posts.css' ) );
	wp_enqueue_style( 'same-category-posts' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__.'\same_category_posts_styles' );

/**
 * Register our admin scripts
 *
 * @return void
 */
function same_category_posts_admin_scripts($hook) {
	wp_register_script( 'same_category-posts-admin-js', plugins_url( 'same-category-posts/js/admin/same-category-posts.js' ), array('jquery') , SAME_CATEGORY_POSTS_VERSION , true );
	wp_enqueue_script( 'same_category-posts-admin-js' );
}
add_action('admin_enqueue_scripts', __NAMESPACE__.'\same_category_posts_admin_scripts');

/**
 * Add styles for widget sections
 *
 */ 
function admin_styles() {
?>
<style>
.same-category-widget-cont h4 {
    padding: 12px 15px;
    cursor: pointer;
    margin: 5px 0;
    border: 1px solid #E5E5E5;
}
.same-category-widget-cont h4:first-child {
	margin-top: 10px;	
}
.same-category-widget-cont h4:last-of-type {
	margin-bottom: 10px;
}
.same-category-widget-cont h4:after {
	float:right;
	font-family: "dashicons";
	content: '\f140';
	-ms-transform: translate(-1px,1px);
	-webkit-transform: translate(-1px,1px);
	-moz-transform: translate(-1px,1px);
	transform: translate(-1px,1px);
	-ms-transition: all 600ms;
	-webkit-transition: all 600ms;
	-moz-transition: all 600ms;
    transition: all 600ms;	
}	
.same-category-widget-cont h4.open:after {
	-ms-transition: all 600ms;
	-webkit-transition: all 600ms;
	-moz-transition: all 600ms;
    transition: all 600ms;	
	-ms-transform: rotate(180deg);
    -webkit-transform: rotate(180deg);
	-moz-transform: rotate(180deg);
	transform: rotate(180deg);
}	
.same-category-widget-cont > div {
	display:none;
	overflow: hidden;
}	
.same-category-widget-cont > div.open {
	display:block;
}	
<?php //disable taxterms?>
select[disabled] {
    background: #ececec !important;
}

select[disabled] option {
    color: #c3c3c3;
}
</style>
<?php
}

add_action( 'admin_print_styles-widgets.php', __NAMESPACE__.'\admin_styles' );

/**
 * Get image size
 *
 * $thumb_w, $thumb_h - the width and height of the thumbnail in the widget settings
 * $image_w,$image_h - the width and height of the actual image being displayed
 *
 * return: an array with the width and height of the element containing the image
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

/**
 * Related Posts Widget Class
 *
 * Shows posts from same category with some configurable options
 */
class Widget extends \WP_Widget {

	function __construct() {
		$widget_ops = array(
			'show_instance_in_rest' => true,
			'classname' => 'same-category-posts', 
			'description' => __('List posts from same category in sidebar based on shown post\'s category')
		);
		parent::__construct('same-category-posts', __('Same Category Posts'), $widget_ops);
	}
	
	/*
		override the thumbnail htmo to insert cropping when needed
	*/
	function post_thumbnail_html( $size ){
		global $post;

		$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( ! $post_thumbnail_id && $this->instance['default_thunmbnail'] ) {
			$post_thumbnail_id = $this->instance['default_thunmbnail'];
		}

		// Don't use the thumbnail, if it is cropped
		$thumbSize = array();
		foreach ( get_intermediate_image_sizes() as $thumb ) {
			if ( in_array( $thumb, array('thumbnail') ) ) {
				$thumbSize['width']  = get_option( "{$thumb}_size_w" );
				$thumbSize['height'] = get_option( "{$thumb}_size_h" );
				$thumbSize['crop']   = (bool) get_option( "{$thumb}_crop" );
			}
		}
		if ( $thumbSize['crop'] ) {
			$thumbSize[0] = $size[0] <= $thumbSize['width'] ? ( $thumbSize['width'] + 1 ) : $size[0];
			$thumbSize[1] = $size[1] <= $thumbSize['height'] ? ( $thumbSize['height'] + 1 ) : $size[1];
		}

		// HTML generated by the core APIs.
		$html = wp_get_attachment_image( $post_thumbnail_id, $thumbSize, false );

		if ( empty( $this->instance['thumb_w'] ) || empty( $this->instance['thumb_h'] ) ){
			return $html; // bail out if no full dimensions defined
		}

		$meta = image_get_intermediate_size($post_thumbnail_id,$size);

		$post_img = wp_get_attachment_metadata($post_thumbnail_id, $size);
		$meta['file'] = basename( $post_img['file'] );

		$origfile = get_attached_file( $post_thumbnail_id, true); // the location of the full file
		$file =	dirname($origfile) .'/'.$meta['file']; // the location of the file displayed as thumb
		list( $width, $height ) = getimagesize($file);  // get actual size of the thumb file
		
		if ($width / $height == $this->instance['thumb_w'] / $this->instance['thumb_h']) {
			// image is same ratio as asked for, nothing to do here as the browser will handle it correctly
			;
		} else if (isset($this->instance['use_css_cropping'])) {
			$image = same_category_posts_get_image_size( $this->instance['thumb_w'], $this->instance['thumb_h'], $width, $height );

			// replace srcset
			$array = array();
			preg_match( '/width="([^"]*)"/i', $html, $array ) ;
			$pattern = "/".$array[1]."w/";
			$html = preg_replace($pattern, $image['image_w']."w", $html);
			// replace size
			$pattern = "/".$array[1]."px/";
			$html = preg_replace($pattern, $image['image_w']."px", $html);
			// replace width
			$pattern = "/width=\"[0-9]*\"/";
			$html = preg_replace($pattern, "width='".$image['image_w']."'", $html);
			// replace height
			$pattern = "/height=\"[0-9]*\"/";
			$html = preg_replace($pattern, "height='".$image['image_h']."'", $html);
			// set margin
			$html = str_replace('<img ','<img style="'.$image['marginAttr'].':-'.$image['marginVal'].'px;height:'.$image['image_h']
				.'px;clip:rect(auto,'.($this->instance['thumb_w']+$image['marginVal']).'px,auto,'.$image['marginVal']
				.'px);width:auto;max-width:initial;" ',$html);
			// wrap span
			$html = '<span style="width:'.$this->instance['thumb_w'].'px;height:'.$this->instance['thumb_h'].'px;">'
				.$html.'</span>';
		} else {
			// if use_css_cropping not used
			// no interface changes: leave without change
		}
		return $html;
	}	
	
	/*
		wrapper to execute the the_post_thumbnail with filters
	*/
	function the_post_thumbnail($size= 'post-thumbnail',$attr='') {
        if (empty($size))  // if junk value, make it a normal thumb
            $size= 'post-thumbnail';
        else if (is_array($size) && (count($size)==2)) {  // good format at least
            // normalize to ints first
            $size[0] = (int) $size[0];
            $size[1] = (int) $size[1];
            if (($size[0] == 0) && ($size[1] == 0)) //both values zero then revert to thumbnail
                $size= 'post-thumbnail';
            // if one value is zero make a square using the other value
            else if (($size[0] == 0) && ($size[1] != 0))
                $size[0] = $size[1];
            else if (($size[0] != 0) && ($size[1] == 0))
                $size[1] = $size[0];
        } else {
            $size= 'post-thumbnail'; // yet another form of junk
        }
        
        // don't use the thumbnail, if it is cropped
        $thumbSize = array();
        foreach ( get_intermediate_image_sizes() as $thumb ) {
            if ( in_array( $thumb, array('thumbnail') ) ) {
                $thumbSize['width']  = get_option( "{$thumb}_size_w" );
                $thumbSize['height'] = get_option( "{$thumb}_size_h" );
                $thumbSize['crop']   = (bool) get_option( "{$thumb}_crop" );
            }
        }
        if ( $thumbSize['crop'] ) {
            $size[0] = $size[0] <= $thumbSize['width'] ? ( $thumbSize['width'] + 1 ) : $size[0];
            $size[1] = $size[1] <= $thumbSize['height'] ? ( $thumbSize['height'] + 1 ) : $size[1];
        }

        $ret = $this->post_thumbnail_html( $size );
        return $ret;
	}

	/**
	 * Calculate the HTML for showing the thumb of a post item.
     * Expected to be called from a loop with globals properly set
	 *
	 * @param  array $instance Array which contains the various settings
	 * @return string The HTML for the thumb related to the post
     *
     * @since 1.0.8
	 */
	function show_thumb($instance) {
        $ret = '';

		if ( function_exists('the_post_thumbnail') && 
				current_theme_supports("post-thumbnails") &&
				isset ( $this->instance["thumb"] ) &&
				has_post_thumbnail() ) {
			$ret .= '<a ';
			$use_css_cropping = isset($this->instance['use_css_cropping']) ? "same-category-post-css-cropping" : "";
			$ret .= 'class="same-category-post-thumbnail ' . $use_css_cropping . '"';
			$ret .= 'href="' . get_the_permalink() . '" title="' . htmlspecialchars( get_the_title() ) . '">';
			$ret .= $this->the_post_thumbnail( array($this->instance['thumb_w'],$this->instance['thumb_h']));
			$ret .= '</a>';
		}
		return $ret;
	}

	/**
	 * Excerpt length filter
	 */
	function excerpt_length_filter( $length ) {
		return $this->instance["excerpt_length"];
	}

	/**
	 * Excerpt more link filter
	 */
	function excerpt_more_filter($more) {
		return ' <a class="cat-post-excerpt-more" href="'. get_permalink() . '">' . esc_html($this->instance["excerpt_more_text"]) . '</a>';
	}
	
	/**
	 * Calculate the HTML for a post item based on the widget settings and post.
     * Expected to be called in an active loop with all the globals set
	 *
	 * @param  array $instance Array which contains the various settings
     * $param  null|integer $current_post_id If on singular page specifies the id of
     *                      the post, otherwise null
	 * @return string The HTML for item related to the post
     *
     * @since 1.0.8
	 */
    function itemHTML($instance,$current_post_id) {
        global $post;
		
        $ret = '<li class="same-category-post-item ' . ($post->ID == $current_post_id ? 'same-category-post-current' : '') . '">';
		
		if( isset( $instance["thumbTop"] ) ) : 
			$ret .= $this->show_thumb($instance);
		endif; 		

		$ret .= '<a class="post-title" href="' . get_the_permalink() . '" rel="bookmark" title="' . htmlspecialchars( get_the_title() ) . '">' . get_the_title() . '</a>';

		if ( isset( $instance['date'] ) ) {			
            if (isset($instance['date_format']) && strlen(trim($instance['date_format'])) > 0)
                $date_format = $instance['date_format']; 
            else if (isset($instance['use_wp_date_format']) && strlen(trim($instance['use_wp_date_format'])) > 0)
				$date_format = "";
			else
                $date_format = "j M Y"; 
				
            $ret .= '<p class="post-date">';
			
            if (isset($instance["date_link"]) && $instance["date_link"])
                $ret .= '<a href="'.get_the_permalink().'">';
				
            $ret .= get_the_date($date_format);
			
            if (isset($instance["date_link"]) && $instance["date_link"])
                $ret .= '</a>';
				
            $ret .= '</p>';
		}
		
		if( !isset( $instance["thumbTop"] ) ) : 
			$ret .= $this->show_thumb($instance);
		endif;
		
		if ( isset ( $instance['excerpt'] ) ) {
			$ret .= apply_filters('the_excerpt', get_the_excerpt());;
		}

		if ( isset ( $instance['comment_num'] ) ) {
			$ret .= '<p class="same-category-post-comment-num">(' . get_comments_number() . ')</p>';
		}
		
		if ( isset( $instance['author'] ) ) {
			$ret .= '<p class="post-author cat-post-author">';
				$ret .=  get_the_author_posts_link();
			$ret .= '</p>';
		}

		$ret .= '</li>';
		return $ret;
	}
	/**

	 * Set for each object_taxonomy the hierarchical post_type as default, if no tax is selected
	 *
	 * @param  array &$instance
	 * @return by ref
	 */
	function initPostTypesAndTaxes(&$instance) {
		$post_types = get_post_types( array( 'publicly_queryable' => true ) );
		foreach ($post_types as $post_type) {
			$object_taxes = get_object_taxonomies( $post_type, 'objects' );

			$set_default = true;
			foreach ( $object_taxes as $tax ) {
				if (isset($instance['include_tax'][$post_type]) && $instance['include_tax'][$post_type]) {
					if ($tax->name == $instance['include_tax'][$post_type])
						$set_default = false;
				}
				// put all taxes and the associated post_type
				if ( ! isset( $instance['post_types'][$tax->name] ) ||
				( isset( $instance['post_types'][$tax->name] ) && ! array_key_exists( $tax->name, $instance['post_types'] ) ) ) {
					if ($tax->hierarchical) {
						$instance['post_types'][$tax->name] = array( 'post_type'=>$post_type, 'hierarchical'=>true );
					} else {
						$instance['post_types'][$tax->name] = array( 'post_type'=>$post_type, 'hierarchical'=>false );
					}
				}
			}
			// put and set only 'default' taxes
			if ($set_default) {
				foreach ( $object_taxes as $tax ) {
					if ($tax->hierarchical) {
						$instance['include_tax'][$post_type] = $tax->name; // set as 'default'
					}
				}
			}
		}
	}

	// Displays a list of posts from same category on single post pages.
	function widget($args, $instance) {
		// Only show widget on post- or archive page.
		if ( !is_single() && !is_archive() || empty( get_the_ID() ) ) {
			return;
		}

		global $wp_query;
		global $post;
		$post_old = $post; // Save the post object.
		if ( is_archive() ) {
			// if archive page
			$current_post_id = 0;
		} else {
			$current_post_id = get_the_ID();
		}

		extract( $args );
		$this->instance = $instance;

		$this->initPostTypesAndTaxes($instance);

		// if archive page
		$include_tax_save = array();
		if( is_archive() ) {
			$term = get_queried_object();
			$post_type = get_post_type($term->slag);
			$include_tax_save = $instance['include_tax'];
			$instance['include_tax'] = array( $post_type => $term->taxonomy );
		} else {
			$include_tax_save = $instance['include_tax'];
		}

		$taxonomies     = null;
		$all_taxonomies = null;
		if ( is_archive() ) {
			// if archive page
			$taxes = array(get_queried_object()->taxonomy);
		} else {
			$taxes = get_object_taxonomies( $post );
		}
		// Get all taxonomies
		$all_terms = array();
		foreach ($taxes as $tax)  {
			$terms = get_terms($tax,array('public'=>true), 'objects');
			foreach ($terms as $id => $name)  {
				$all_terms[$tax][] = $name->term_id;
			}
		}

		// Get post taxonomies
		$categories = null;
		foreach ($taxes as $tax) {
			$post_type = $instance['post_types'][$tax]['post_type'];
			if ($tax == $instance['include_tax'][$post_type]) {
				if ( is_archive() ) {
					// if archive page
					$terms = array( ( object ) array(
						"term_id" => get_queried_object()->term_id,
						"name" => get_queried_object()->name,
						) );
				} else {
					$terms = get_the_terms($post->ID, $tax);
				}
				if ($terms) {
					foreach ($terms as $term) {
						$taxonomies[$tax][] = $term->term_id;
					}
				}
				if ( is_archive() ) {
					// if archive page
					$categories = array( ( object ) array(
						"term_id"  => get_queried_object()->term_id,
						"name"     => get_queried_object()->name,
						"taxonomy" => get_queried_object()->taxonomy,
						) );
				} else {
					$categories = get_the_terms($post->ID,$tax);
				}
			}
		}
		
		// If the current post has no terms for the selected taxonomy, don't render the widget.
		if ( empty( $categories ) || is_wp_error( $categories ) ) {
    	return;
		}

		// Excerpt length filter
		if ( isset($instance["excerpt_length"]) && $instance["excerpt_length"] > 0 ) {
			add_filter( 'excerpt_length', array( $this,'excerpt_length_filter' ), 9999 );
		}
		
		$valid_sort_orders = array('date', 'title', 'comment_count', 'rand');
		if ( isset($instance['sort_by']) && in_array($instance['sort_by'], $valid_sort_orders) ) {
			$sort_by = $instance['sort_by'];
			$sort_order = (bool) isset( $instance['asc_sort_order'] ) ? 'ASC' : 'DESC';
		} else {
			// by default, display latest first
			$sort_by = 'date';
			$sort_order = 'DESC';
		}

		// Excerpt more_text
		if( isset($instance["excerpt_more_text"]) && ltrim($instance["excerpt_more_text"]) != '' )
		{
			add_filter('excerpt_more', array($this,'excerpt_more_filter'));
		}		
		
		// deprecate >= 1.0.12: 'exclude_categories' get 'exclude_terms'
		// Exclude categories
		if (!isset($instance['exclude_terms']['category']) && isset($instance['exclude_categories']) && $instance['exclude_categories'])
			$instance['exclude_terms'] = array( 'category' => $instance['exclude_categories']);

		// Query args
		$args = array(
			'orderby' => $sort_by,
            'order'   => $sort_order
		);

		$args['post_type'] = array( get_post_type( $post ) );

		if ($taxonomies) {
			$term_query_in = array('relation' => 'OR');
			$term_query_exclude = null;
			$term_query_not_in  = null;
			foreach ( $taxonomies as $tax=>$terms) {
				$exclude_terms = array();
				if ( 
					in_array($tax, $include_tax_save) && 
					isset($instance['exclude_terms']) && 
					$instance['exclude_terms'] && 
					array_key_exists($tax, $instance['exclude_terms']) 
				) {
					$terms = array_diff($terms, $instance['exclude_terms'][$tax]);
					$exclude_terms = $instance['exclude_terms'][$tax];
				}
				// all terms associated with the current post
				if ($terms) {
					$term_query_in[] = array(
						'taxonomy' => $tax,
						'field' => 'term_id',
						'terms' => $terms,
						'include_children' => isset($instance['exclude_children']) && $instance['exclude_children'] ? false : true,
						'operator' => 'IN',
						);
				} else {
					return;
				}

				// excluded terms
				if ( $exclude_terms ) {
					$term_query_exclude[] = array(
						'taxonomy' => $tax,
						'field' => 'term_id',
						'terms' => $exclude_terms,
						'include_children' => isset( $instance['exclude_no_children'] ) && $instance['exclude_no_children'] ? false : true,
						'operator' => 'NOT IN',
						);
				}
			}
		}

		$term_query[] = array('relation' => 'AND');
		$term_query[] = $term_query_in;
		$term_query[] = $term_query_exclude;

        if (is_array($term_query))
            $args['tax_query'] = $term_query;

		$args['post__not_in'] = array();
		if (isset( $instance['exclude_current_post'] ) && $instance['exclude_current_post']) {
			$args['post__not_in'] = array( $current_post_id );
		}

		if(isset( $instance['exclude_sticky_posts'] ) && $instance['exclude_sticky_posts']) {
			foreach (get_option( 'sticky_posts' ) as $value) {
				$args['post__not_in'][] = $value;
			}
		}

		$args['posts_per_page'] = (isset($instance['num']) && $instance['num']) ? $instance['num'] : -1;

		$my_query = new \WP_Query($args);

		if( $my_query->have_posts() )
		{
			echo $before_widget;

			// Widget title
			if( !isset ( $instance["hide_title"] ) ) {
				if( isset( $instance["separate_categories"] ) && $instance["separate_categories"] ) { 
					// Separate categories: title to array
					foreach($categories as $cat) {
						$widgetHTML[$cat->name]['ID'] = $cat->term_id;
						if( isset ( $instance["title_link"] ) ) {
							$title = '<a href="' . get_category_link( $cat ) . '">'. $cat->name . '</a>';
							if(isset($instance['title']) && strpos($instance['title'], '%cat-all%') !== false)
								$title = str_replace( "%cat-all%", $title, $instance['title']);
							else if(isset($instance['title']) && strpos($instance['title'], '%cat%') !== false)
								$title = str_replace( "%cat%", $title, $instance['title']);
							$widgetHTML[$cat->name]['title'] = $title;
						} else {
							$title = $cat->name;
							if(isset($instance['title']) && strpos($instance['title'], '%cat-all%') !== false)
								$title = str_replace( "%cat-all%", $title, $instance['title']);
							else if(isset($instance['title']) && strpos($instance['title'], '%cat%') !== false)
								$title = str_replace( "%cat%", $title, $instance['title']);
							$widgetHTML[$cat->name]['title'] = $title;
						}
					}
				} else {
					// ! Separate categories: echo
					echo $before_title;
					if( isset ( $instance["title_link"] ) ) {
						$linkList = "";
						foreach($categories as $cat) {
							if(isset($instance['exclude_terms']) && $instance['exclude_terms'] && in_array($cat->term_id,$instance['exclude_terms']))
								continue;
							$linkList .= '<a href="' . get_category_link( $cat ) . '">'. $cat->name . '</a>, ';
						}
						$linkList = trim($linkList, ", ");
						if( isset($instance['title']) && $instance['title'] ) { 			// use placeholders if title is not empty
							if(strpos($instance['title'], '%cat-all%') !== false || 
								strpos($instance['title'], '%cat%') !== false) {			// all-category placeholder is used
								if(strpos($instance['title'], '%cat-all%') !== false)
									$linkList = str_replace( "%cat-all%", $linkList, $instance['title']);
								else if(strpos($instance['title'], '%cat%') !== false)
									$linkList = str_replace( "%cat%", '<a href="' . get_category_link( $categories[0] ) . '">'. $categories[0]->name . '</a>', $instance['title']);
							} else 															// no category placeholder is used
								$linkList = '<a href="' . get_category_link( $categories[0] ) . '">'. $instance['title'] . '</a>';
						}
						echo htmlspecialchars_decode(apply_filters('widget_title',$linkList));
					} else {
						$categoryNames = "";
						if ($categories) {
							foreach ($categories as $key => $val) {
								if (
									isset($instance['exclude_terms']) && 
									$instance['exclude_terms'] && 
									array_key_exists($val->taxonomy,$instance['exclude_terms']) && 
									in_array($val->term_id,$instance['exclude_terms'][$val->taxonomy])
								) {
									continue;
								}
								$categoryNames .= $val->name . ", ";
							}
							$categoryNames = trim($categoryNames, ", ");
						}
					
						if( isset($instance['title']) && $instance['title'] ) {				// use placeholders if title is not empty
							if(strpos($instance['title'], '%cat-all%') !== false)			// all-category placeholder is used
								$categoryNames = str_replace( "%cat-all%", $categoryNames, $instance['title']);
							else if(strpos($instance['title'], '%cat%') !== false)			// one-category placeholder is used
								$categoryNames = str_replace( "%cat%", $categories[0]->name, $instance['title']);
							else
								$categoryNames = $instance['title'];
						}
						echo htmlspecialchars_decode(apply_filters('widget_title',$categoryNames));
					}
					echo $after_title;
				}
			}
			// /Widget title
			
			// Post list
			echo "<ul>\n";
			while ($my_query->have_posts())
			{
				$my_query->the_post();
				
				if( isset( $instance["separate_categories"] ) && $instance["separate_categories"] ) {
					// Separate categories: get itemHTML to array
					$object_taxes = get_object_taxonomies( $post, 'objects' );
					$post_categories = null;
					foreach ( $object_taxes as $tax ) {
						$post_type = $instance['post_types'][$tax->name]['post_type'];
						if ($tax->name == $instance['include_tax'][$post_type]) {
							$post_categories = get_the_terms($post->ID,$tax->name);
							break;
						}
					} 
					foreach ($post_categories as $val) {
						if ( in_array( $val, $categories ) ) {
							$widgetHTML[$val->name][$post->ID]['itemHTML'] = $this->itemHTML($instance,$current_post_id);
							$widgetHTML[$val->name][$post->ID]['ID'] = $post->ID;
						}
					}
				} else {
					// ! Separate categories: get itemHTML and echo
					echo $this->itemHTML($instance,$current_post_id);
				}
			} // end while

			if (isset( $instance["separate_categories"] ) && $instance["separate_categories"]) {
				// Separate categories: echo
				$isOnPage = array();
				foreach($widgetHTML as $val) {
					// widget title
					$haveItemHTML = false;
					$ret = $before_title . htmlspecialchars_decode(apply_filters('widget_title',isset($val['title'])?$val['title']:"")) . $after_title;
					$count = 1;
					$num_per_cat = (isset($instance['num_per_cate'])&&$instance['num_per_cate']!=0?($instance['num_per_cate']):99999);
					foreach($val as $key) {
						if(is_array($key) && array_key_exists('itemHTML', $key)) {
							if( !in_array($key['ID'], $isOnPage) ) {
								if($count <= $num_per_cat) {
									$ret .= $key['itemHTML'];
									$haveItemHTML = true;
									$isOnPage[] = $key['ID'];
								} else
									break;
								$count++;
							}
						}
					}
				if ($haveItemHTML)
					echo $ret;
				}
			}

			echo "</ul>\n";
			// end Post list
			
			echo $after_widget;
		}

		remove_filter( 'excerpt_length',  array( $this,'excerpt_length_filter' ) );
		remove_filter('excerpt_more', array($this,'excerpt_more_filter'));

		$post = $post_old; // Restore the post object.
	}

	/**
	 * Update the options
	 *
	 * @param  array $new_instance
	 * @param  array $old_instance
	 * @return array
	 */
	function update($new_instance, $old_instance) {
		
		return $new_instance;
	}

	/**
	 * The widget configuration form back end.
	 *
	 * @param  array $instance
	 * @return void
	 */
	function form($instance) {
	
		// ToDo seperate_cat get seperate_tax (if update, settings)
		
		$this->initPostTypesAndTaxes($instance);

		$instance = wp_parse_args( ( array ) $instance, array(
			'title'                => '',
			'hide_title'           => '',
			'separate_categories'  => '',
			'num_per_cate'         => '',
			'num'                  => '',
			'exclude_no_children'  => '',
			'exclude_children'     => '',
			'sort_by'              => '',
			'asc_sort_order'       => '',
			'title_link'           => '',
			'exclude_categories'   => '',
			'exclude_terms'        => array(),
			'include_tax'          => array(),
			'post_types'           => array(),
			'exclude_current_post' => '',
			'exclude_sticky_posts' => '',
			'author'               => '',
			'date_format'          => '',
			'use_wp_date_format'   => '',
			'date_link'            => '',
			'excerpt'              => '',
			'excerpt_length'       => '',
			'excerpt_more_text'    => '',
			'comment_num'          => '',
			'date'                 => '',
			'thumb'                => '',
			'thumbTop'             => '',
			'thumb_w'              => '',
			'thumb_h'              => '',
			'use_css_cropping'     => ''
		) );

		$title                = $instance['title'];
		$hide_title           = $instance['hide_title'];
		$separate_categories  = $instance['separate_categories'];
		$num_per_cate         = $instance['num_per_cate'];
		$num                  = $instance['num'];
		$exclude_no_children  = $instance['exclude_no_children'];
		$exclude_children     = $instance['exclude_children'];
		$sort_by              = $instance['sort_by'];
		$asc_sort_order       = $instance['asc_sort_order'];
		$title_link           = $instance['title_link'];
		$exclude_categories   = $instance['exclude_categories'];
		$exclude_terms        = $instance['exclude_terms'];
		$include_tax          = $instance['include_tax'];
		$post_types           = $instance['post_types'];
		$exclude_current_post = $instance['exclude_current_post'];
		$exclude_sticky_posts = $instance['exclude_sticky_posts'];
		$author               = $instance['author'];
		$date_format          = $instance['date_format'];
		$use_wp_date_format   = $instance['use_wp_date_format'];
		$date_link            = $instance['date_link'];
		$excerpt              = $instance['excerpt'];
		$excerpt_length       = $instance['excerpt_length'];
		$excerpt_more_text    = $instance['excerpt_more_text'];
		$comment_num          = $instance['comment_num'];
		$date                 = $instance['date'];
		$thumb                = $instance['thumb'];
		$thumbTop             = $instance['thumbTop'];
		$thumb_w              = $instance['thumb_w'];
		$thumb_h              = $instance['thumb_h'];
		$use_css_cropping     = $instance['use_css_cropping'];		
		
		?>
		<div class="same-category-widget-cont">
			<h4 data-panel="title"><?php _e('Title')?></h4>
			<div>
				<p>
					<label for="<?php echo $this->get_field_id("title_link"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("title_link"); ?>" name="<?php echo $this->get_field_name("title_link"); ?>"<?php checked( (bool) $instance["title_link"], true ); ?> />
						<?php _e( 'Make widget title link' ); ?>
					</label>
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id("hide_title"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("hide_title"); ?>" name="<?php echo $this->get_field_name("hide_title"); ?>"<?php checked( (bool) $instance["hide_title"], true ); ?> />
						<?php _e( 'Hide title' ); ?>
					</label>
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id("title"); ?>">
						<?php _e( 'Title *' ); ?>:
						<input 
							style="width:80%;" 
							class="widefat" 
							id="<?php echo $this->get_field_id("title"); ?>" 
							name="<?php echo $this->get_field_name("title"); ?>" 
							type="text" 
							value="<?php echo esc_attr($instance['title']); ?>" />
						<div style="border-left:5px solid #F1F1F1;padding-left:10px;">* Placeholder: </br>'%cat%' - One category (the first if more assigned)</br>'%cat-all%' - All assigned categories for the shown post</div>
					</label>
				</p>
			</div>
			<h4 data-panel="filter"><?php _e('Filter')?></h4>
			<div>
				<?php	
					// get taxonomies except for the built-in
					$args = array(
								'public' => true
								); 
					$taxs = get_taxonomies( $args, 'objects' );
					
					// now get all tags
					$args = array(
								'hide_empty' => false, // we want to show not yet populated terms as well
								'fields' => 'id=>name' // return array of names matched to ids
							);
					$collect_post_types = "";
					$collect_post_types_end = false;
					foreach ($taxs as $key=>$tax) {
						$taxname   = $tax->name;
						$terms     = get_terms($taxname,$args);
						$post_type = $post_types[$taxname]['post_type'];

						if ($terms) {
							
							if ($collect_post_types != $post_type) {
								echo ( $collect_post_types_end == false ) ? '' : '</div>';
								echo 'Show <b>' . get_post_type_object( $post_type )->label . '</b> with:<br>';
								echo '<div style="border-left:5px solid #F1F1F1;padding-left:10px;">';
								$collect_post_types = $post_type;
								$collect_post_types_end = true;
							}
							?>
							<p>
								<label>
									<input 
										name="<?php echo $this->get_field_name('include_tax['.$post_type.']'); ?>" 
										data-taxname-and-post-type="<?php echo $taxname.'-'.$post_type ?>" 
										onchange="javascript:scpwp_namespace.toggleSelectTaxPanel(this)" 
										type="radio" 
										class="checkbox" 
										id="<?php echo $this->get_field_id( $taxname ); ?>" 
										value="<?php echo $taxname ?>"
										<?php checked( (bool) ($instance['include_tax'][$post_type] == $taxname), true ); ?> />
									<?php printf( __( 'Same "%s" and exclude:' ), esc_html($tax->labels->name)); ?>
								</label>
							</p>
							<?php

							$selected = array();
							if (isset($instance['exclude_terms'][$taxname]))
								$selected = $instance['exclude_terms'][$taxname];
							else if (isset($instance["exclude_categories"]) && $instance["exclude_categories"] && $taxname == 'category') // deprecate >= 1.0.12: 'exclude_categories' get 'exclude_terms'
								$selected = $instance["exclude_categories"];

							$style_display_attr = ($instance['include_tax'][$post_type] == $taxname)?'block':'none';

							echo '<div data-post-type='.$post_type.' class=\'scpwp-exclude-taxterms-'.$taxname.'-panel\' style="display:'.$style_display_attr.'">';
							echo '<select multiple="multiple" name="'.$this->get_field_name('exclude_terms').'['.$taxname.'][]" id="'.$this->get_field_id('exclude_terms['.$taxname.']').'">';
							foreach ($terms as $id => $name)  {
								$sel = '';
								if (in_array($id,$selected))
									$sel = ' selected="selected"';
								echo '<option value="'.$id.'"'.$sel.'>'.esc_html($name).'</option>';
							}
							echo '</select>';
							echo '<p>(CTRL / ⌘ + Mouseclick: Multiselection and clear)</p>';
							echo '</div>';
						}
					}

					echo '</div>'; // $collect_post_types_end

					?>

					<p>
						<label for="<?php echo $this->get_field_id("exclude_no_children"); ?>">
							<input type="checkbox" class="checkbox" 
								id="<?php echo $this->get_field_id("exclude_no_children"); ?>" 
								name="<?php echo $this->get_field_name("exclude_no_children"); ?>"
								<?php checked( (bool) $instance["exclude_no_children"], true ); ?> />
									<?php _e( 'Perform the exclusion without children' ); ?>
						</label>
					</p>

				<p>
					<label for="<?php echo $this->get_field_id("sort_by"); ?>">
						<?php _e('Sort by'); ?>:
						<select id="<?php echo $this->get_field_id("sort_by"); ?>" name="<?php echo $this->get_field_name("sort_by"); ?>">
							<option value="date"<?php selected( $instance["sort_by"], "date" ); ?>>Date</option>
							<option value="title"<?php selected( $instance["sort_by"], "title" ); ?>>Title</option>
							<option value="comment_count"<?php selected( $instance["sort_by"], "comment_count" ); ?>>Number of comments</option>
							<option value="rand"<?php selected( $instance["sort_by"], "rand" ); ?>>Random</option>
						</select>
					</label>
				</p>

				<p>
					<label for="<?php echo $this->get_field_id("asc_sort_order"); ?>">
						<input type="checkbox" class="checkbox" 
							id="<?php echo $this->get_field_id("asc_sort_order"); ?>" 
							name="<?php echo $this->get_field_name("asc_sort_order"); ?>"
							<?php checked( (bool) $instance["asc_sort_order"], true ); ?> />
								<?php _e( 'Reverse sort order (ascending)' ); ?>
					</label>
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id("separate_categories"); ?>">
						<input onchange="javascript:scpwp_namespace.toggleSeparateCategoriesPanel(this)" type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("separate_categories"); ?>" name="<?php echo $this->get_field_name("separate_categories"); ?>"<?php checked( (bool) $instance["separate_categories"], true ); ?> />
						<?php _e( 'Separate terms (If more than one assigned)' ); ?>
					</label>
				</p>

				<p class="scpwp-separate-categories-panel" style="border-left:5px solid #F1F1F1;padding-left:10px;display:<?php echo (isset($separate_categories) && $separate_categories) ? 'block' : 'none'?>">
					<label for="<?php echo $this->get_field_id("num_per_cate"); ?>">
						<?php _e('Max. number of posts per separated categories'); ?>:
						<input style="width: 30%; text-align: center;" id="<?php echo $this->get_field_id("num_per_cate"); ?>" name="<?php echo $this->get_field_name("num_per_cate"); ?>" type="number" min="0" value="<?php echo absint($instance["num_per_cate"]); ?>" size='3' />
					</label>
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id("num"); ?>">
						<?php _e('Number of posts to show (overall)'); ?>:
						<input style="width:30%;" style="text-align: center;" id="<?php echo $this->get_field_id("num"); ?>" name="<?php echo $this->get_field_name("num"); ?>" type="number" min="0" value="<?php echo absint($instance["num"]); ?>" size='3' />
					</label>
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id("exclude_current_post"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("exclude_current_post"); ?>" name="<?php echo $this->get_field_name("exclude_current_post"); ?>"<?php checked( (bool) $instance["exclude_current_post"], true ); ?> />
						<?php _e( 'Exclude current post' ); ?>
					</label>
				</p>

				<p>
					<label for="<?php echo $this->get_field_id("exclude_sticky_posts"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("exclude_sticky_posts"); ?>" name="<?php echo $this->get_field_name("exclude_sticky_posts"); ?>"<?php checked( (bool) $instance["exclude_sticky_posts"], true ); ?> />
						<?php _e( 'Exclude sticky posts' ); ?>
					</label>
				</p>

				<p>
					<label for="<?php echo $this->get_field_id("exclude_children"); ?>">
						<input type="checkbox" class="checkbox" 
							id="<?php echo $this->get_field_id("exclude_children"); ?>" 
							name="<?php echo $this->get_field_name("exclude_children"); ?>"
							<?php checked( (bool) $instance["exclude_children"], true ); ?> />
								<?php _e( 'Exclude  children' ); ?>
					</label>
				</p>

			</div>
			<h4 data-panel="thumbnails"><?php _e('Thumbnails')?></h4>
			<div>
				<?php 
					if ( function_exists('the_post_thumbnail') && current_theme_supports("post-thumbnails") ) : 
				?>				
					<p>
						<label for="<?php echo $this->get_field_id("thumb"); ?>">
							<input onchange="javascript:scpwp_namespace.toggleShowPostThumbnailPanel(this)" type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("thumb"); ?>" name="<?php echo $this->get_field_name("thumb"); ?>"<?php checked( (bool) $instance["thumb"], true ); ?> />
							<?php _e( 'Show post thumbnail' ); ?>
						</label>
					</p>
					
					<div class="scpwp-show-post-thumbnail-panel" style="border-left:5px solid #F1F1F1;padding-left:10px;display:<?php echo (isset($thumb) && $thumb) ? 'block' : 'none'?>">
						<p>
							<label for="<?php echo $this->get_field_id("thumbTop"); ?>">
								<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("thumbTop"); ?>" name="<?php echo $this->get_field_name("thumbTop"); ?>"<?php checked( (bool) $instance["thumbTop"], true ); ?> />
								<?php _e( 'Thumbnail to top' ); ?>
							</label>
						</p>

						<p>
							<label>
								<?php _e('Thumbnail dimensions (in pixels)'); ?>:<br />
								<label for="<?php echo $this->get_field_id("thumb_w"); ?>">
									Width: <input class="widefat" style="width:30%;" type="number" min="1" id="<?php echo $this->get_field_id("thumb_w"); ?>" name="<?php echo $this->get_field_name("thumb_w"); ?>" value="<?php echo $instance["thumb_w"]; ?>" />
								</label>
								
								<label for="<?php echo $this->get_field_id("thumb_h"); ?>">
									Height: <input class="widefat" style="width:30%;" type="number" min="1" id="<?php echo $this->get_field_id("thumb_h"); ?>" name="<?php echo $this->get_field_name("thumb_h"); ?>" value="<?php echo $instance["thumb_h"]; ?>" />
								</label>
							</label>
						</p>

						<p>
							<label for="<?php echo $this->get_field_id("use_css_cropping"); ?>">
								<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("use_css_cropping"); ?>" name="<?php echo $this->get_field_name("use_css_cropping"); ?>"<?php checked( (bool) $instance["use_css_cropping"], true ); ?> />
								<?php _e( 'CSS crop to requested size' ); ?>
							</label>
						</p>
					</div>
				<?php 
					endif; 
				?>
			</div>
			<h4 data-panel="details"><?php _e('Post details')?></h4>
			<div>
				<p>
					<label for="<?php echo $this->get_field_id("excerpt"); ?>">
						<input onchange="javascript:scpwp_namespace.toggleShowPostExcerptPanel(this)" type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("excerpt"); ?>" name="<?php echo $this->get_field_name("excerpt"); ?>"<?php checked( (bool) $instance["excerpt"], true ); ?> />
						<?php _e( 'Show post excerpt' ); ?>
					</label>
				</p>
				
				<div class="scpwp-show-post-excerpt-panel" style="border-left:5px solid #F1F1F1;padding-left:10px;display:<?php echo (isset($excerpt) && $excerpt) ? 'block' : 'none'?>">
					<p>
						<label for="<?php echo $this->get_field_id("excerpt_length"); ?>">
							<?php _e( 'Excerpt length (in words):' ); ?>
						</label>
						<input style="width:30%; text-align: center;" placeholder="<?php _e('55')?>" type="number" min="0" id="<?php echo $this->get_field_id("excerpt_length"); ?>" name="<?php echo $this->get_field_name("excerpt_length"); ?>" value="<?php echo $instance["excerpt_length"]; ?>" size="3" />
					</p>
					
					<p>
						<label for="<?php echo $this->get_field_id("excerpt_more_text"); ?>">
							<?php _e( 'Excerpt \'more\' text:' ); ?>
						</label>
						<input class="widefat" style="width:50%;" placeholder="<?php _e('... more')?>" id="<?php echo $this->get_field_id("excerpt_more_text"); ?>" name="<?php echo $this->get_field_name("excerpt_more_text"); ?>" type="text" value="<?php echo esc_attr($instance["excerpt_more_text"]); ?>" />
					</p>
				</div>
				
				<p>
					<label for="<?php echo $this->get_field_id("comment_num"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("comment_num"); ?>" name="<?php echo $this->get_field_name("comment_num"); ?>"<?php checked( (bool) $instance["comment_num"], true ); ?> />
						<?php _e( 'Show number of comments' ); ?>
					</label>
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id("date"); ?>" onchange="javascript:scpwp_namespace.toggleDatePanel(this)">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("date"); ?>" name="<?php echo $this->get_field_name("date"); ?>"<?php checked( (bool) $instance["date"], true ); ?> />
						<?php _e( 'Show post date' ); ?>
					</label>
				</p>
				<div class="cpwp_ident scpwp-data-panel-date" style="display:<?php echo ((bool) $date) ? 'block' : 'none'?>">
					<p>
						<label for="<?php echo $this->get_field_id("use_wp_date_format"); ?>" onchange="javascript:scpwp_namespace.toggleUseWPDateFormatPanel(this)">
							<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("use_wp_date_format"); ?>" name="<?php echo $this->get_field_name("use_wp_date_format"); ?>"<?php checked( (bool) $instance["use_wp_date_format"], true ); ?> />
							<?php _e( 'Use the WordPress Settings > General for the date format','same-posts' ); ?>
						</label>
					</p>
					<div class="scpwp-data-panel-date-format" style="display:<?php echo ((bool) $use_wp_date_format) ? 'none' : 'block'?>">
						<p>
							<label for="<?php echo $this->get_field_id("date_format"); ?>">
								<?php _e( 'Date format:','same-posts' ); ?>
							</label>
							<input class="text" placeholder="j M Y" id="<?php echo $this->get_field_id("date_format"); ?>" name="<?php echo $this->get_field_name("date_format"); ?>" type="text" value="<?php echo esc_attr($instance["date_format"]); ?>" size="8" />
						</p>
					</div>
					<p>
						<label for="<?php echo $this->get_field_id("date_link"); ?>">
							<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("date_link"); ?>" name="<?php echo $this->get_field_name("date_link"); ?>"<?php checked( (bool) $instance["date_link"], true ); ?> />
							<?php _e( 'Make widget date link','same-posts' ); ?>
						</label>
					</p>
				</div>
				
				<p>
					<label for="<?php echo $this->get_field_id("author"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("author"); ?>" name="<?php echo $this->get_field_name("author"); ?>"<?php checked( (bool) $instance["author"], true ); ?> />
						<?php _e( 'Show post author' ); ?>
					</label>
				</p>
			</div>
			<hr>
			
			<p style="text-align:right;">
				Follow us on <a target="_blank" href="https://www.facebook.com/TipTopPress">Facebook</a> and 
				<a target="_blank" href="https://twitter.com/TipTopPress">Twitter</a></br></br>
			</p>
		</div>
		<?php

	}

}

function register_widget() {
    return \register_widget(__NAMESPACE__.'\Widget');
}

add_action( 'widgets_init', __NAMESPACE__.'\register_widget' );

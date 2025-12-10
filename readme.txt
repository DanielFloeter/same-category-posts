=== Same Category Posts ===
Contributors: kometschuh, mkrdip
Donate link: https://wordpress.org/support/plugin/same-category-posts/reviews/?filter=5
Tags: related posts, related, custom post type, products, events
Requires at least: 3.0
Tested up to: 6.9
Stable tag: 1.1.19
License: GPLv2 or later 
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Show posts related to the current category or other custom post types.

== Description ==

Same Category Posts shows a list of related posts with a same Post Type to the current post. The widget is only shown on single post pages. Forked from [Related Posts Widget](https://wordpress.org/plugins/related-posts-widget).

= Tip Top Press =
We're [Tip Top Press](http://tiptoppress.com/) and create widgets for Wordpress. If you want to know about what we're working on and you are interested in backgrounds then you can read all newes storys on our [blog](http://tiptoppress.com/blog/?utm_source=wp.org&utm_medium=readme.txt&utm_campaign=same+category+posts).

= Features =

* Shows a list of related posts.
* Option which Post Type should be related to the current post.
* Custom Post Types support.
* Child category and terms support.
* Archive page support.
* Option exclude one or multi categories or terms.
* Option to filter by popular posts (by comment count).
* Option [separate categories](http://tiptoppress.com/new-dynamic-layout-feature-separate-categories/) if more than one is assigned.
* Set how many posts to show (overall and by category).
* Option exclude current post, sticky posts or children.
* Option exclude categories and terms without exclude their children.
* Placeholders in title string (e.g. "There are a lot of %cat%-News." -> "There are a lot of Tech-News.").
* Filter hook for the post titles 'widget_title'.
* Option to show post thumbnail and set width & height.
* Option to [crop thumbnails dimensions with CSS](http://tiptoppress.com/css-image-crop/).
* Option to put thumbnail on top.
* Option to make the widget title link to the category page.
* Option to show/hide the title.
* Option to show the post excerpt and how long (in words).
* Option change excerpt 'more' text.
* Option to show the post date, author and comment count.
* Multiple widgets.

= Placeholder =

In text boxes **%cat%** will replaced with the (first assigned) category name, e.g. "There are a lot of %cat%-News." -> "There are a lot of Tech-News."

And **%cat-all%** will replaced with all assigned category name, e.g. "Special offers for %cat-all%!" -> "Special offers for houses, flats, apartments."

= Contribute =
While using this plugin if you find any bug or any conflict, please submit an issue at 
[Github](https://github.com/DanielFloeter/same-category-posts) (If possible with a pull request). 

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of Related Posts Widget, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “Same Category Posts” and click Search Plugins. Once you’ve found plugin, you can install it by simply clicking “Install Now”. Then, go to plugins page of WordPress admin activate the plugin. Now, goto the Widgets page of the Appearance section and configure the Related Posts widget.

= Manual installation =

1. Download the plugin.
2. Upload it to the plugins folder of your blog.
3. Goto the Plugins section of the WordPress admin and activate the plugin.
4. Now, goto the Widgets page of the Appearance section and configure the Related Posts widget.

Read more about installting plugins at [WordPress Codex](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

== Frequently Asked Questions ==

= Post title filter example =
To use the the hook add a filter 'widget_title' in wp-content\Themes\[your-Theme]\functions.php:

function cruise_shortcode_iconship( $html ) {
	return "prefix-" . $html . "-suffix";
}
add_filter( 'widget_title', 'cruise_shortcode_iconship' );

= The feature image dimention are not correctly displayed? =
Enable the option "Use CSS cropping". This option don't care about stored setting (Settings > Media) or added image sizes. [What is 'CSS feature image cropping'](http://tiptoppress.com/css-image-crop/)?

= Excerpt length filter doesn't works =
When a post has no manual excerpt WordPress generates an excerpt automatically by selecting the first 55 words of the post or the first words number setted by the excerpt filter length. This means the excerpt length filter works only with automatic excerpt. If you manually added an excerpt, you'd want it all to be shown.
WordPress Codex: [Excerpt, automatic excerpt, and teaser](http://codex.wordpress.org/Excerpt#Excerpt.2C_automatic_excerpt.2C_and_teaser)

= I want the title as a link pointing to the selected Categorie page? =
Enable the check box "Make widget title link".

= Exclude (-terms) text area =
To multiselect or clear terms in the "exclude"-text area hold CTRL-key + mouse click

== Screenshots ==

1. Option to exclude categories (and terms) and filter by popular posts (by comment count).
2. Front end of the widget using a default WordPress Theme.
3. Option 'Separate categories' (If more than one assigned) will separate and link to all assigned categories.
4. The widget configuration dialog.

== Changelog ==

= 1.2.0 - Dezember 20 2021  =
* Gutenberg Block support

= 1.1.19 - Dec 10 2025  =
* Cleanup for anchor title

= 1.1.18 - March 01 2023  =
* Cleanup for anchor title

= 1.1.17 - June 18 2022  =
* Bugfix Excerpt length overrides other filters

= 1.1.16 - June 05 2022  =
* Bugfix Showing to much posts from not related categories

= 1.1.15 - January 27 2022  =
* Bugfix HTML entities in title attributes

= 1.1.14 - January 17 2022  =
* Bugfix Exclude

= 1.1.13 - July 29 2021  =
* Include archive pages

= 1.1.12 - July 20 2021  =
* jQuery Helper warnings

= 1.1.11 - April 06 2021  =
* Bugfix Thumbnail to top

= 1.1.10 - April 04 2021  =
* Bugfix Title splits with Safari

= 1.1.9 - March 19 2021  =
* Bugfix Same Taxonomies
* Bugfix Collect Post Types

= 1.1.8 - March 01 2021  =
* Bugfix Duplicate post content

= 1.1.7 - December 04 2020  =
* Bugfix Ensure the current post object

= 1.1.6 - October 08 2020  =
* Support child categories
* Bugfix Not assigned categories exclude child categories

= 1.1.5 - Septemer 27 2020  =
* Bugfix Posts are from all categories
* Bugfix Don't use the thumbnail, if it is cropped

= 1.1.4 - September 17 2020  =
* Bugfix Finding the current post is overwritten by other widgets

= 1.1.3 - November 19 2019  =
* Add option to exclude children
* Add option to exclude without exclude children

= 1.1.2 - November 03 2019  =
* Bugfix for exclude sticky posts

= 1.1.1 - November 02 2019  =
* Exclude sticky posts

= 1.1.0 - November 02 2017  =
* Custom Post Type support

= 1.0.12 - June 15 2017  =
* Bugfix for if widget title as a link, there are HTML special characters in the rendered output
* Bugfix for no posts are displayed, if some categories are excluded

= 1.0.11 - May 20 2017  =
* Add a filter hook for the post titles 'widget_title'
* Add option to format the date

= 1.0.10 - January 01 2017  =
* Patch panels do not open

= 1.0.9 - December 31 2016  =
* Add option to exclude multi categories (pull-request from [arielcannal](https://github.com/arielcannal))
* Add panels on the admin sites

= 1.0.8 - Juli 07 2016 =
* Add option seperate categories if more than one is assigned
* Add option change excerpt 'more' text.
* Title show and link to all referenced categories
* Additional placeholder %cat-all% gets (show and link) all referenced categories

= 1.0.7 - June 26 2016 =
* Show all assigned categories

= 1.0.6 - June 05 2016 =
* Added option to show post author

= 1.0.5 - April 02 2016 =
* Added Custom Post Types support

= 1.0.4 - April 02 2016 =
* Add option CSS cropping for thumbnails.

= 1.0.3 =
* Placeholder in title string
* Added Option exclude current post
* CSS class for current post
* Bugfixes

= 1.0.2 =
* Added Option to change ordering of posts
* Added Option to make the widget title link to the category page
* Added Option to put thumbnail on top
* Added Option to show/hide the title
* Fixed no background bug.

= 1.0.1 =
* Option exclude a category.

= 1.0 =
* Initial release.

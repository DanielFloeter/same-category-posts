<?php
/**
 * Bootstrap for the WordPress-free unit test suite.
 *
 * Only loads files that have no WordPress dependency. Everything that needs
 * WordPress is tested by hand for now; see plan.md, section 5.
 *
 * @package samePosts
 */

require_once dirname( dirname( __DIR__ ) ) . '/includes/block-attributes.php';
require_once dirname( dirname( __DIR__ ) ) . '/includes/image-size.php';

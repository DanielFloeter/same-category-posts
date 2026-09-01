<?php
/**
 * Bootstrap for the WordPress-free unit test suite.
 *
 * Only loads files that have no WordPress dependency. The WordPress
 * integration suite lives in tests/ and has its own bootstrap.
 *
 * @package samePosts
 */

require_once dirname( dirname( __DIR__ ) ) . '/includes/block-attributes.php';

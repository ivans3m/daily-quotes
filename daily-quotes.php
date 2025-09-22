<?php
/**
 * Plugin Name: Daily Quotes
 * Description: Display non-repeating daily text/html from named sets with Gutenberg block and shortcode.
 * Version: 0.4.0
 * Author: Ivan s3m
 * Author URI: https://ivan.diuldia.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: daily-quotes
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DQ_PLUGIN_FILE', __FILE__ );
define( 'DQ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload minimal files.
require_once DQ_PLUGIN_DIR . 'includes/cpts.php';
require_once DQ_PLUGIN_DIR . 'includes/rotation.php';
require_once DQ_PLUGIN_DIR . 'includes/shortcode.php';
require_once DQ_PLUGIN_DIR . 'includes/block.php';
require_once DQ_PLUGIN_DIR . 'includes/rest.php';

register_activation_hook( __FILE__, function() {
	// Register CPTs then flush.
	require_once DQ_PLUGIN_DIR . 'includes/cpts.php';
	dq_register_cpts();
	// Ensure Default set exists on activation
	if ( function_exists( 'dq_get_default_set_id' ) ) {
		dq_get_default_set_id();
	}
	flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
});
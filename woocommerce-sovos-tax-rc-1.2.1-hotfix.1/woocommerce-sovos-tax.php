<?php
/*
Plugin Name: WooCommerce Sovos Tax Handler
Plugin URI: https://builtmighty.com
Description: Retrieve and Handle Tax Calculations from Sovos Tax Compliance Software.
Version: 1.2.0
Author: Built Mighty
Author URI: https://builtmighty.com
Copyright: Built Mighty
Text Domain: woo-sovos
Copyright © 2024 Built Mighty. All Rights Reserved.
*/

/**
 * Set namespace.
 */
namespace BuiltMighty\WOO_SOVOS;

/**
 * Disallow direct access.
 */
if( !defined( 'WPINC' ) ) { die; }

/**
 * Constants.
 */
define( 'WOO_SOVOS_VERSION', '1.1.0' );
define( 'WOO_SOVOS_NAME', 'woo-sovos' );
define( 'WOO_SOVOS_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WOO_SOVOS_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WOO_SOVOS_DOMAIN', 'woo-sovos' );

/** 
 * On activation.
 */
register_activation_hook( __FILE__, 'BuiltMighty\WOO_SOVOS\woo_sovos_activation' );
function woo_sovos_activation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * On deactivation.
 */
register_deactivation_hook( __FILE__, 'BuiltMighty\WOO_SOVOS\woo_sovos_deactivation' );
function woo_sovos_deactivation() {

    // Flush rewrite rules.
    flush_rewrite_rules();

}

/**
 * Load plugin.
 * 
 * @since   1.0.0
 */
require WOO_SOVOS_PATH . 'inc/class-plugin.php';

/**
 * Run plugin.
 * 
 * @since   1.0.0
 */
function run_woo_sovos_plugin() {

    // Get plugin.
    $plugin = new \BuiltMighty\WOO_SOVOS\Woo_Sovos_Plugin();

    // Run.
    $plugin->run();

}
run_woo_sovos_plugin();

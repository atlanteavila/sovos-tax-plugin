<?php
/**
 * WC CLI Sovos Tax Command.
 *
 * @package WooCommerce Sovos Tax
 */

if ( ! defined( 'ABSPATH' ) )
	exit;

if ( ! defined( 'WP_CLI' ) )
	return;


/**
 * WC CLI Tax Rate Command.
 * 
 * @since 1.2.0
 */
class WC_CLI_SOVOS_Tax_Command {
	/**
	 * Delete all tax rates.
	 *
	 * ## EXAMPLES
	 *
	 * wp wc-tax-rate delete_all
	 * 
	 * @return void
	 *
	 */
	public function delete_all() {
		global $wpdb;
		$wpdb->query( "TRUNCATE {$wpdb->prefix}woocommerce_tax_rates" );
		$wpdb->query( "TRUNCATE {$wpdb->prefix}woocommerce_tax_rate_locations" );
		WP_CLI::success( 'All tax rates deleted.' );
	}
}
WP_CLI::add_command( 'wc-tax-rate', 'WC_CLI_SOVOS_Tax_Command' );

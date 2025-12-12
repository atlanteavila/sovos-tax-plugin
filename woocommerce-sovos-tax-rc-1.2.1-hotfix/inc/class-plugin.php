<?php
/**
 * Plugin.
 * 
 * @since   1.0.0
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS;

class Woo_Sovos_Plugin {

    /**
     * Loader.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     Woo_Sovos_Loader       $loader     Registers all hooks.
     */
    protected $loader;

    /**
     * Identifier.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string          $identifier     The plugin identifier.
     */
    protected $plugin_name;

    /**
     * Version.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string          $version        The plugin version.
     */
    protected $version;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Set version.
        $this->version = WOO_SOVOS_VERSION;

        // Set name.
        $this->plugin_name = WOO_SOVOS_NAME;

        // Load dependencies.
        $this->load_dependencies();

        // Load private hooks.
        $this->private_hooks();

        // Load public hooks.
        $this->public_hooks();

    }

    /**
     * Load dependencies.
     * 
     * @since   1.0.0
     * @access  private
     */
    private function load_dependencies() {

        // Loader.
        require_once WOO_SOVOS_PATH . 'inc/class-loader.php';

        // Logging.
        require_once WOO_SOVOS_PATH . 'utilities/class-log.php';

        // Utilities.
        require_once WOO_SOVOS_PATH . 'utilities/global-utilities.php';

        // Vendor.
        require_once WOO_SOVOS_PATH . 'inc/vendor/tax-service/tax-service.php';

        // Private.
        require_once WOO_SOVOS_PATH . 'private/class-private.php';

        // Public.
        require_once WOO_SOVOS_PATH . 'public/class-public.php';

        // WC CLI SOVOS Tax Command.
        require_once WOO_SOVOS_PATH . 'public/class-wc-cli-sovos-tax-command.php';

        // Initiate loader.
        $this->loader = new \BuiltMighty\WOO_SOVOS\Woo_Sovos_Loader();

    }

    /**
     * Register admin hooks.
     */
    private function private_hooks() {

        // Set new admin.
        $private = new \BuiltMighty\WOO_SOVOS\Woo_Sovos_Private( $this->get_name(), $this->get_version() );

        // Add actions.
        $this->loader->add_action( 'admin_enqueue_scripts', $private, 'enqueue_styles' );
        // $this->loader->add_action( 'admin_enqueue_scripts', $private, 'enqueue_scripts' );

        // Add filters.

    }

    /**
     * Register public hooks.
     */
    private function public_hooks() {

        // Set new public.
        $public = \BuiltMighty\WOO_SOVOS\Woo_Sovos_Public::get_instance( $this->get_name(), $this->get_version() );

        // Add actions.

        // Add Temporary Tax Rates Actions on Init.
        $this->loader->add_action( 'init', $public, 'add_temp_tax_rates_actions' );

        // Create API Orders Tax Class on Init.
        $this->loader->add_action( 'init', $public, 'create_api_orders_tax_class' );

        // Enqueue styles.
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );

        // Add Sovos Transaction ID Tax to New Order.
        $this->loader->add_action( 'woocommerce_new_order', $public, 'add_sovos_transaction_id_to_new_order', 10, 2 );

        // Add Sovos Tax Rate to New Order Item.
        $this->loader->add_action( 'woocommerce_checkout_create_order_tax_item', $public, 'add_sovos_tax_rate_to_new_order_item', 10, 3 );

        // Send Order Data to Sovos when Order Status changes to Processing
        $this->loader->add_action( 'woocommerce_order_status_changed', $public, 'send_order_data_to_sovos', 10, 4 );

        // Set Tax Rates on Cart Items before calculating totals
        $this->loader->add_action( 'woocommerce_before_calculate_totals', $public, 'set_tax_rates_on_cart_items', 10, 1 );

        // Display Sovos Transaction ID in Admin.
        $this->loader->add_action( 'woocommerce_admin_order_totals_after_tax', $public, 'display_sovos_transaction_id_in_admin', 10, 1 );

        // Transfer Cart Item Meta to Order Item.
        $this->loader->add_action( 'woocommerce_checkout_create_order_line_item', $public, 'transfer_cart_item_meta_to_order_item', 10, 4 );

        // Delete Temporary Tax Rates.
        $this->loader->add_action( 'woocommerce_checkout_order_processed', $public, 'delete_temporary_tax_rates', 10, 1 );

        // Display Sovos Tax Info as Meta in Admin.
        $this->loader->add_action( 'woocommerce_after_order_itemmeta', $public, 'display_sovos_tax_in_admin', 10, 3 );

        // Send Refund Request to Sovos.
        $this->loader->add_action( 'woocommerce_refund_created', $public, 'send_refund_request', 10, 2 );

        // Update Sovos Transaction ID on Refund.
        $this->loader->add_action( 'woocommerce_order_status_refunded', $public, 'order_status_refunded', 10, 1 );

        // Display Sovos Transaction ID on Refund.
        $this->loader->add_action( 'woocommerce_after_order_refund_item_name', $public, 'display_sovos_transaction_id_on_refund' );

        // Prevent Tax Calculation Unless on Checkout
        $this->loader->add_action( 'woocommerce_before_calculate_totals', $public, 'prevent_tax_calulation_unless_on_checkout', 10, 1 );

        // Clear Cache After Checkout
        $this->loader->add_action( 'woocommerce_checkout_order_created', $public, 'clear_tax_quote_cache', 20, 1 );

        // Add filters.

        // Add a notice on the cart page
        $this->loader->add_filter( 'woocommerce_cart_totals_taxes_total_html', $public, 'add_tax_notice_to_cart', 10, 1 );

        // Hide Tax Rate ID Meta
        $this->loader->add_filter( 'woocommerce_hidden_order_itemmeta', $public, 'hide_tax_rate_id_meta', 10, 1 );

        // Replace the matched tax rates with a custom rate.
        $this->loader->add_filter( 'woocommerce_matched_tax_rates', $public, 'replace_matched_tax_rates', 10, 5 );

        // Run WC Product Get Tax Class Methods
        $this->loader->add_filter( 'woocommerce_product_get_tax_class', $public, 'woocommerce_product_get_tax_class', 10, 2 );

        // Hide API Orders Tax Class from Tax Options
        $this->loader->add_filter( 'woocommerce_get_sections_tax', $public, 'hide_api_orders_tax_class_from_tax_options' );

        // Hide API Orders Tax Class from Additional Tax Classes
        $this->loader->add_filter( 'woocommerce_tax_settings', $public, 'hide_api_orders_tax_class_from_additional_tax_classes' );

        // Ensure API Orders Tax Class remains empty
        $this->loader->add_filter( 'woocommerce_tax_rate_added', $public, 'ensure_api_tax_class_remains_empty', 10, 2 );

    }

    /**
     * Run.
     * 
     * @since   1.0.0
     */
    public function run() {

        // Run the loader.
        $this->loader->run();

    }

    /**
     * Get plugin name.
     * 
     * @since   1.0.0
     * @return  string      The plugin name.
     */
    public function get_name() {

        // Return plugin name.
        return $this->plugin_name;

    }

    /**
     * Get plugin version.
     * 
     * @since   1.0.0
     * @return  string      The plugin version.
     */
    public function get_version() {

        // Return plugin version.
        return $this->version;

    }

}

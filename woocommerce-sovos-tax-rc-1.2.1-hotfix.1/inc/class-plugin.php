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
        $this->loader->add_action( 'woocommerce_product_options_general_product_data', $private, 'render_product_exemption_field' );
        $this->loader->add_action( 'woocommerce_admin_process_product_object', $private, 'save_product_exemption_field', 10, 1 );
        $this->loader->add_action( 'show_user_profile', $private, 'render_user_exemption_fields' );
        $this->loader->add_action( 'edit_user_profile', $private, 'render_user_exemption_fields' );
        $this->loader->add_action( 'personal_options_update', $private, 'save_user_exemption_fields', 10, 1 );
        $this->loader->add_action( 'edit_user_profile_update', $private, 'save_user_exemption_fields', 10, 1 );
        // $this->loader->add_action( 'admin_enqueue_scripts', $private, 'enqueue_scripts' );

        // Add filters.

    }

    /**
     * Register public hooks.
     */
    /**
 * Register public hooks.
 */
    private function public_hooks()
    {

        // Set new public.
        $public = \BuiltMighty\WOO_SOVOS\Woo_Sovos_Public::get_instance($this->get_name(), $this->get_version());

        // ───────── Actions ─────────

        // Add Temporary Tax Rates Actions on Init.
        $this->loader->add_action('init', $public, 'add_temp_tax_rates_actions');

        // Create API Orders Tax Class on Init.
        $this->loader->add_action('init', $public, 'create_api_orders_tax_class');

        // Enqueue styles.
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');

        // Insert checkout UI helpers.
        $this->loader->add_action('woocommerce_after_checkout_shipping_form', $public, 'render_calculate_taxes_button');
        $this->loader->add_action('woocommerce_checkout_update_order_review', $public, 'capture_quote_state_from_request');

        // Add Sovos Transaction ID Tax to New Order (note-only; does not persist tax).
        $this->loader->add_action('woocommerce_new_order', $public, 'add_sovos_transaction_id_to_new_order', 10, 2);

        // (Legacy) Tried to set a tax-item label; keep for now.
        $this->loader->add_action('woocommerce_checkout_create_order_tax_item', $public, 'add_sovos_tax_rate_to_new_order_item', 10, 3);

        // ✅ Persist per-line taxes onto order items (copy _sovos_tax AND set per-line tax arrays)
        // Use priority 20 so it runs after other line-item meta is attached.
        $this->loader->add_action('woocommerce_checkout_create_order_line_item', $public, 'transfer_cart_item_meta_to_order_item', 20, 4);

        // ✅ Finalize order-level tax items from the per-line arrays before save
        // High priority so it runs after all items are created.
        $this->loader->add_action('woocommerce_checkout_create_order', $public, 'finalize_order_taxes_on_create', 100, 2);

        // Send Order Data to Sovos when Order Status changes to Processing
        $this->loader->add_action('woocommerce_order_status_changed', $public, 'send_order_data_to_sovos', 10, 4);

        // Set Tax Rates on Cart Items before calculating totals (UI estimate only)
        // $this->loader->add_action('woocommerce_before_calculate_totals', $public, 'set_tax_rates_on_cart_items', 10, 1);

        // Display Sovos Transaction ID in Admin.
        $this->loader->add_action('woocommerce_admin_order_totals_after_tax', $public, 'display_sovos_transaction_id_in_admin', 10, 1);

        // ❌ (TEMP DISABLE) Deleting rates immediately can break re-calcs/refunds. Comment out for now.
        $this->loader->add_action( 'woocommerce_checkout_order_processed', $public, 'delete_temporary_tax_rates', 10, 1 );

        // Display Sovos Tax Info as Meta in Admin.
        $this->loader->add_action('woocommerce_after_order_itemmeta', $public, 'display_sovos_tax_in_admin', 10, 3);

        // Refund flow: ping Sovos + annotate
        $this->loader->add_action('woocommerce_refund_created', $public, 'send_refund_request', 10, 2);
        $this->loader->add_action('woocommerce_order_status_refunded', $public, 'order_status_refunded', 10, 1);
        $this->loader->add_action('woocommerce_after_order_refund_item_name', $public, 'display_sovos_transaction_id_on_refund');

        // Prevent Tax Calculation Unless on Checkout (keeps catalog/cart light)
        $this->loader->add_action('woocommerce_before_calculate_totals', $public, 'prevent_tax_calulation_unless_on_checkout', 10, 1);

        // Clear Sovos quote cache after checkout
        $this->loader->add_action('woocommerce_checkout_order_created', $public, 'clear_tax_quote_cache', 20, 1);

        // ───────── Filters ─────────

        // Cart-page notice
        $this->loader->add_filter('woocommerce_cart_totals_taxes_total_html', $public, 'add_tax_notice_to_cart', 10, 1);

        // Hide _sovos_tax meta in order item UI
        $this->loader->add_filter('woocommerce_hidden_order_itemmeta', $public, 'hide_tax_rate_id_meta', 10, 1);

        // ✅ Replace matched tax rates with a real, inserted Woo rate (make sure your function calls insert_tax_rate)
        $this->loader->add_filter('woocommerce_matched_tax_rates', $public, 'replace_matched_tax_rates', 999, 5);

        // Product tax class logic (exempt users / REST protection)
        $this->loader->add_filter('woocommerce_product_get_tax_class', $public, 'woocommerce_product_get_tax_class', 10, 2);

        // Hide “API Orders” class from tax settings UI and keep it empty
        $this->loader->add_filter('woocommerce_get_sections_tax', $public, 'hide_api_orders_tax_class_from_tax_options');
        $this->loader->add_filter('woocommerce_tax_settings', $public, 'hide_api_orders_tax_class_from_additional_tax_classes');
        $this->loader->add_filter('woocommerce_tax_rate_added', $public, 'ensure_api_tax_class_remains_empty', 10, 2);

        // Quote freshness + AJAX endpoints
        $this->loader->add_action('wp_ajax_sovos_mark_quote_stale', $public, 'ajax_mark_quote_stale');
        $this->loader->add_action('wp_ajax_nopriv_sovos_mark_quote_stale', $public, 'ajax_mark_quote_stale');
        $this->loader->add_action('wp_ajax_sovos_refresh_quote', $public, 'ajax_refresh_quote');
        $this->loader->add_action('wp_ajax_nopriv_sovos_refresh_quote', $public, 'ajax_refresh_quote');
        $this->loader->add_action('woocommerce_checkout_process', $public, 'enforce_fresh_quote_before_checkout');
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

<?php
/**
 * Public.
 * 
 * @since   1.0.0
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS;

class Woo_Sovos_Public {

    /**
     * Instance
     * 
     * @since   1.2.0
     * @access  private
     * @var     Woo_Sovos_Public $instance - The plugin instance.
     */
    private static $instance;

    /**
     * Get instance.
     * 
     * @param string $plugin_name - Plugin Name
     * @param string $plugin_version - Plugin Version
     * 
     * @return  Woo_Sovos_Public $instance - The plugin instance.
     * @access private
     * @since   1.2.0
     */
    public static function get_instance( $plugin_name, $plugin_version ) {

        // Check if instance exists.
        if( null == self::$instance )
            self::$instance = new self( $plugin_name, $plugin_version );

        // Return instance.
        return self::$instance;
    }

    /**
     * Plugin name.
     */
    private $plugin_name;

    /**
     * Plugin version.
     */
    private $plugin_version;

    /**
     * Tax Service.
     */
    protected $tax_service;

    /**
     * API Tax Class Name
     */
    protected $api_tax_class_name = 'API Orders';

    /**
     * API Tax Class Slug
     */
    protected $api_tax_class_slug = 'api-orders';

    /**
     * In-request Sovos quote cache to prevent duplicate calls within the same PHP request.
     */
    protected $runtime_quote_cache = [];

    /**
     * Construct.
     * 
     * @since   1.0.0
     * @param   string      $plugin_name        The name of the plugin.
     * @param   string      $plugin_version     The version of the plugin.
     */
    public function __construct( $plugin_name, $plugin_version ) {

        // Set plugin name.
        $this->plugin_name = $plugin_name;

        // Set plugin version.
        $this->plugin_version = $plugin_version;

    }

    /**
     * Cache Bust
     * 
     * @param   string      $file       The file to cache bust.
     * 
     * @return  string     $version    The version of the file.
     * 
     * @since   1.0.0
     */
    public function cache_bust_version( $file ) {
        $version = file_exists( $file ) ?
            $this->plugin_version . '.' . strval( filemtime( $file ) ) :
            $this->plugin_version;
        return $version;
    }

    /**
     * Enqueue styles.
     * 
     * @since   1.0.0
     */
    public function enqueue_styles() {

        // Styles.
        $file = 'public/assets/css/styles.css';
        wp_enqueue_style( $this->plugin_name, WOO_SOVOS_URI . $file, [], $this->cache_bust_version( WOO_SOVOS_PATH . $file ), 'all' );

    }

    /**
     * Enqueue scripts.
     * 
     * @since   1.0.0
     */
    public function enqueue_scripts() {

        // Scripts.
        $file = 'public/assets/js/scripts.js';
        wp_enqueue_script( $this->plugin_name, WOO_SOVOS_URI . $file, ['jquery'], $this->cache_bust_version( WOO_SOVOS_PATH . $file ), false );

    }

    /**
     * Gate checkout updates until the checkout form has enough data.
     */
    public function enqueue_checkout_gating_script() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        $checkout_handle = 'wc-checkout';

        if ( ! wp_script_is( $checkout_handle, 'enqueued' ) ) {
            return;
        }

        $inline_script = <<<'JS'
(function ($) {
    if (!$.fn.trigger || !document.body) {
        return;
    }

    const originalTrigger = $.fn.trigger;

    if (originalTrigger.__sovosDeferredApplied) {
        return;
    }

    let allowImmediate = false;
    let lastAddressKey = null;
    let lastCompleteness = false;
    let addressPollTimer = null;
    let debounceTimer = null;
    let isUpdatingCheckout = false;
    let lastUpdateTimestamp = 0;
    let dirtyBilling = false;
    let dirtyShipping = false;

    const fieldFilled = (selector, options = {}) => {
        const { minLength = 1 } = options;
        const field = $(selector);

        if (!field.length) {
            return true;
        }

        const value = typeof field.val() === 'string' ? field.val().trim() : '';
        return value.length >= minLength;
    };

    const getPostcodeMinLength = (country) => {
        switch ((country || '').toUpperCase()) {
            case 'US':
                return 5;
            case 'CA':
                return 3;
            case 'AU':
                return 4;
            case 'GB':
                return 3;
            default:
                return 3;
        }
    };

    const getFieldValue = (selector) => {
        const field = $(selector);
        return field.length ? String(field.val() || '').trim() : '';
    };

    const isBillingComplete = () => {
        const billingCountry = getFieldValue('#billing_country');
        const postcodeMinLength = getPostcodeMinLength(billingCountry);

        return [
            '#billing_country',
            '#billing_address_1',
            '#billing_city',
            '#billing_state'
        ].every(fieldFilled) && fieldFilled('#billing_postcode', { minLength: postcodeMinLength });
    };

    const isShippingDifferent = () => $('#ship-to-different-address-checkbox').is(':checked');

    const isShippingComplete = () => {
        if (!isShippingDifferent()) {
            return isBillingComplete();
        }

        const shippingCountry = getFieldValue('#shipping_country');
        const postcodeMinLength = getPostcodeMinLength(shippingCountry);

        return [
            '#shipping_country',
            '#shipping_address_1',
            '#shipping_city',
            '#shipping_state'
        ].every(fieldFilled) && fieldFilled('#shipping_postcode', { minLength: postcodeMinLength });
    };

    const canUpdateCheckout = () => isBillingComplete() && isShippingComplete();

    const buildAddressKey = () => {
        const base = {
            billing: {
                country: getFieldValue('#billing_country'),
                address1: getFieldValue('#billing_address_1'),
                city: getFieldValue('#billing_city'),
                state: getFieldValue('#billing_state'),
                postcode: getFieldValue('#billing_postcode'),
            },
            shipToDifferent: isShippingDifferent(),
        };

        if (isShippingDifferent()) {
            base.shipping = {
                country: getFieldValue('#shipping_country'),
                address1: getFieldValue('#shipping_address_1'),
                city: getFieldValue('#shipping_city'),
                state: getFieldValue('#shipping_state'),
                postcode: getFieldValue('#shipping_postcode'),
            };
        }

        return JSON.stringify(base);
    };

    const forceUpdate = () => {
        const now = Date.now();
        const cooldownMs = 1200;

        if (isUpdatingCheckout || now - lastUpdateTimestamp < cooldownMs) {
            return;
        }
        lastUpdateTimestamp = now;
        isUpdatingCheckout = true;
        allowImmediate = true;
        try {
            originalTrigger.call($(document.body), 'update_checkout');
        } finally {
            allowImmediate = false;
        }
    };

    const handleAddressChange = () => {
        const addressKey = buildAddressKey();
        const complete = canUpdateCheckout();
        const becameComplete = complete && !lastCompleteness;
        const changedWhileComplete = complete && lastCompleteness && addressKey !== lastAddressKey;

        lastAddressKey = addressKey;
        lastCompleteness = complete;
    };

    const startAddressPolling = (durationMs = 8000, intervalMs = 250) => {
        if (addressPollTimer) {
            clearInterval(addressPollTimer);
            addressPollTimer = null;
        }

        const start = Date.now();
        addressPollTimer = setInterval(() => {
            handleAddressChange();

            if (Date.now() - start >= durationMs) {
                clearInterval(addressPollTimer);
                addressPollTimer = null;
            }
        }, intervalMs);
    };

    const scheduleInitialRechecks = () => {
        const delays = [0, 150, 500];
        delays.forEach((delay) => {
            setTimeout(handleAddressChange, delay);
        });

        startAddressPolling();
    };

    const setDirty = (type, isDirty = true) => {
        if (type === 'billing') {
            dirtyBilling = isDirty;
        } else if (type === 'shipping') {
            dirtyShipping = isDirty;
        }
        syncPlaceOrderButton();
    };

    const syncPlaceOrderButton = () => {
        const needsSave = dirtyBilling || (isShippingDifferent() && dirtyShipping);
        const $placeOrder = $('#place_order');
        if (!$placeOrder.length) {
            return;
        }
        if (needsSave) {
            $placeOrder.attr('disabled', 'disabled');
        } else {
            $placeOrder.removeAttr('disabled');
        }
    };

    const showErrorNotice = (message) => {
        $(document.body).trigger('checkout_error', [message]);
    };

    const saveAddressAndUpdate = (type) => {
        if (type === 'billing' && !isBillingComplete()) {
            showErrorNotice('Please complete your billing address before saving.');
            return;
        }

        if (type === 'shipping' && isShippingDifferent() && !isShippingComplete()) {
            showErrorNotice('Please complete your shipping address before saving.');
            return;
        }

        setDirty(type, false);
        forceUpdate();
    };

    const addSaveButtons = () => {
        // Use the existing billing save button if present.
        const billingSaveBtn = $('#toggle-billing-details');
        if (billingSaveBtn.length) {
            billingSaveBtn.off('click.sovosSave').on('click.sovosSave', (event) => {
                event.preventDefault();
                event.stopPropagation();
                saveAddressAndUpdate('billing');
            });
        }

        const shippingContainer = $('.woocommerce-shipping-fields');
        const ensureShippingButton = () => {
            const existing = shippingContainer.find('.sovos-save-shipping');
            if (!isShippingDifferent()) {
                existing.remove();
                return;
            }
            if (shippingContainer.length && !existing.length) {
                const shippingBtn = $('<button type="button" class="button sovos-save-shipping" style="margin-top:8px">Save shipping address</button>');
                shippingBtn.on('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    saveAddressAndUpdate('shipping');
                });
                shippingContainer.append(shippingBtn);
            } else {
                existing.text('Save shipping address');
            }
        };

        ensureShippingButton();
        $(document.body).on('change', '#ship-to-different-address-checkbox', ensureShippingButton);
    };

    const blockPlaceOrderWhenDirty = () => {
        const form = $('form.checkout');
        form.on('submit', function (event) {
            if (dirtyBilling || (isShippingDifferent() && dirtyShipping)) {
                event.preventDefault();
                showErrorNotice('Please save your address(es) to refresh tax before placing the order.');
                syncPlaceOrderButton();
                return false;
            }
            return true;
        });
    };

    const debouncedHandleAddressChange = (delayMs = 250) => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            handleAddressChange();
        }, delayMs);
    };

    const watchAddressChanges = () => {
        const selectors = [
            '#billing_country',
            '#billing_address_1',
            '#billing_city',
            '#billing_state',
            '#billing_postcode',
            '#shipping_country',
            '#shipping_address_1',
            '#shipping_city',
            '#shipping_state',
            '#shipping_postcode',
            '#ship-to-different-address-checkbox'
        ];

        selectors.forEach((selector) => {
            $(document.body).on('change', selector, handleAddressChange);
            $(document.body).on('keyup', selector, () => debouncedHandleAddressChange());

            if (selector.indexOf('billing_') !== -1) {
                $(document.body).on('change keyup', selector, () => setDirty('billing'));
            } else if (selector.indexOf('shipping_') !== -1) {
                $(document.body).on('change keyup', selector, () => setDirty('shipping'));
            }
        });

        $(document.body).on('updated_checkout wc_address_i18n_ready', handleAddressChange);

        $(document.body).on('change', '#ship-to-different-address-checkbox', () => {
            setDirty('shipping', isShippingDifferent());
            handleAddressChange();
            startAddressPolling();
        });
    };

    const registerManualTriggers = () => {
        const registry = window.sovosDeferUpdateTriggers || {};

        registry.isBillingComplete = isBillingComplete;
        registry.isShippingComplete = isShippingComplete;
        registry.hasPendingUpdate = () => false;
        registry.flushPendingUpdate = () => {};
        registry.forceUpdate = forceUpdate;

        window.sovosDeferUpdateTriggers = registry;
    };

    $.fn.trigger = function () {
        const args = Array.prototype.slice.call(arguments);
        const eventType = args[0];
        const normalizedType = (eventType && eventType.type) ? eventType.type : eventType;

        if (normalizedType === 'update_checkout' && this.is(document.body) && !allowImmediate) {
            return this;
        }

        return originalTrigger.apply(this, args);
    };

    originalTrigger.__sovosDeferredApplied = true;

    $(function () {
        registerManualTriggers();
        watchAddressChanges();
        addSaveButtons();
        blockPlaceOrderWhenDirty();

        // Capture initial state (including values filled by WooCommerce after DOM ready) and trigger an update if needed.
        scheduleInitialRechecks();
        syncPlaceOrderButton();

        // Clear in-flight guard when WooCommerce finishes or errors.
        $(document.body).on('updated_checkout checkout_error', () => {
            isUpdatingCheckout = false;
        });
    });
})(jQuery);
JS;

        wp_add_inline_script( $checkout_handle, $inline_script, 'after' );

    }

    /**
     * Check if API Credentials Are Set
     * 
     * @return bool - True if the API credentials are set, false otherwise.
     * 
     * @since   1.0.0
     */
    public function are_api_credentials_set() {
        $api_credentials_set = true;

        // Check if the constants are defined.
        if (
            ! defined( 'SOVOS_API_USERNAME' ) ||
            ! defined( 'SOVOS_API_PASSWORD' ) ||
            ! defined( 'SOVOS_API_KEY' ) ||
            ! defined( 'SOVOS_API_URL' )
        ) :
            $api_credentials_set = false;
        endif;

        return $api_credentials_set;
    }

    /**
     * Set Tax Service
     * 
     * @return \App\TaxService - The tax service.
     * 
     * @since   1.0.0
     * @throws  Exception  If the API credentials are not set.
     */
    public function set_tax_service() {
        // Instantiate a new TaxService.
        return new \App\TaxService(
            SOVOS_API_USERNAME,
            SOVOS_API_PASSWORD,
            SOVOS_API_ORG_CD,
            SOVOS_API_KEY,
            SOVOS_API_URL
        );
    }

    /**
     * Get Tax Service
     * 
     * @return \App\TaxService - The tax service.
     * 
     * @since   1.0.0
     */
    public function get_tax_service() {
        // Check if the tax service is set.
        $this->tax_service = $this->tax_service ? : $this->set_tax_service();

        return $this->tax_service;
    }

    /**
     * Get WooCommerce Session
     * 
     * @return mixed \WC_Session|bool - The WooCommerce session if it is set, false otherwise.
     * 
     * @since   1.1.0
     */
    public function get_wc_session() {
        if ( ! function_exists( 'WC' ) )
            return false;

        if ( ! WC()->session )
            return false;

        return  WC()->session;
    }

    /**
     * Get WooCommerce Cart
     * 
     * @return mixed \WC_Cart|bool - The WooCommerce session if it is set, false otherwise.
     * 
     * @since   1.1.0
     */
    public function get_cart() {
        if ( ! function_exists( 'WC' ) )
            return false;

        if ( ! WC()->cart )
            return false;

        return  WC()->cart;
    }

    /**
     * Is Address Empty
     * 
     * @param array - $customer_address The customer's address.
     * 
     * @return bool - True if the address is empty, false otherwise.
     * 
     * @since   1.0.0
     */
    public function is_address_empty( $customer_address ) {
            return ( 
                empty( $customer_address['address'] ) ||
                empty( $customer_address['city'] ) ||
                empty( $customer_address['state'] ) ||
                empty( $customer_address['postcode'] ) ||
                empty( $customer_address['country'] )
            );
    }

    /**
     * Is Phone Orders AJAX
     * 
     * @return bool - True if the request is a phone orders AJAX request, false otherwise.
     * 
     * @since   1.2.1
     */
    function is_phone_orders_ajax() {
        if (
            (
                isset( $_POST['action'] ) &&
                strpos( $_POST['action'], 'phone-orders' ) !== false
            ) ||
            (
                isset( $_POST['method'] ) &&
                $_POST['method'] == 'recalculate'
            )
        )
            return true;

        return false;
    }

    /**
     * Get Customer Address
     * 
     * @param string - $type The type of address to get.
     * 
     * @return mixed array|null - The customer's address if it is set, null otherwise.
     * 
     * @since   1.0.0
     */
    public function get_customer_address( $type = 'billing' ) {
        // Get Address from Order.
        $object = null;

        // TODO: Add a WordPress Filter so this can be checked outside of the plugin.
        // The plugin shouldn't be specifically catering to another third party plugin that is not WooCommerce.
        if ( ! is_admin() || $this->is_phone_orders_ajax() ) :
            $object = WC()->customer;
        else :
            global $post;
            $post_id  = $post ? $post->ID : $_GET['post'];
            $order_id = $post_id ? : $_POST['order_id'];
            $order    = wc_get_order( $order_id );
            $object   = $order;
        endif;

        if ( $object ) :
            $customer_address = array();

            if ( $type == 'shipping' ) :
                // Get the user's address from the WooCommerce customer object.
                $customer_address['address']  = $object->get_shipping_address_1();
                $customer_address['city']     = $object->get_shipping_city();
                $customer_address['state']    = $object->get_shipping_state();
                $customer_address['postcode'] = $object->get_shipping_postcode();
                $customer_address['country']  = $object->get_shipping_country();
            endif;

            // $customer_address elements will be empty if $type is not shipping OR if shipping address is not set.
            // Default to Billing Address.
            if ( $this->is_address_empty( $customer_address ) ) {
                // Look at the appropriate set first, then fall back to the other.
                $order_of_prefixes = ($type === 'shipping') ? array('shipping_', 'billing_') : array('billing_', 'shipping_');

                foreach ($order_of_prefixes as $p) {
                    $country = isset($_POST[$p . 'country']) ? wc_clean(wp_unslash($_POST[$p . 'country'])) : '';
                    $state = isset($_POST[$p . 'state']) ? wc_clean(wp_unslash($_POST[$p . 'state'])) : '';
                    $postcode = isset($_POST[$p . 'postcode']) ? wc_clean(wp_unslash($_POST[$p . 'postcode'])) : '';
                    $city = isset($_POST[$p . 'city']) ? wc_clean(wp_unslash($_POST[$p . 'city'])) : '';
                    $addr1 = isset($_POST[$p . 'address_1']) ? wc_clean(wp_unslash($_POST[$p . 'address_1'])) : '';

                    if ($country && ($state || $postcode || $city)) {
                        $customer_address['address'] = $addr1 ?: 'N/A';
                        $customer_address['city'] = $city;
                        $customer_address['state'] = $state;
                        $customer_address['postcode'] = $postcode;
                        $customer_address['country'] = $country;
                        break;
                    }
                }
            }

        endif; // endif ( $object ) :

        // If address is empty try to get it from the $_POST array.
        if (
            $this->is_address_empty( $customer_address ) &&
            isset(
                $_POST['country'],
                $_POST['state'],
                $_POST['postcode'],
                $_POST['city']
            )
        ) :
            $customer_address['address']  = 'N/A'; // Address line 1 is not available in the $_POST array.
            $customer_address['city']     = $_POST['city'];
            $customer_address['state']    = $_POST['state'];
            $customer_address['postcode'] = $_POST['postcode'];
            $customer_address['country']  = $_POST['country'];
        endif;

        return $customer_address;
    }

    /**
     * Get Store Address
     * 
     * @return array - Store Address
     * 
     * @since   1.0.0
     */
    public function get_store_address() {
        // Get the store address.
        return array(
            'address'  => WC()->countries->get_base_address(),
            'city'     => WC()->countries->get_base_city(),
            'state'    => WC()->countries->get_base_state(),
            'postcode' => WC()->countries->get_base_postcode(),
            'country'  => WC()->countries->get_base_country(),
        );
    }

    /**
     * Get From Address
     * 
     * @return array - From Address
     * 
     * @since   1.0.0
     */
    public function get_from_address() {
        $store_address = $this->get_store_address();

        return $store_address;
    }

    /**
     * Set From Address
     * 
     * @return mixed - The from address.
     * 
     * @since   1.0.0
     */
    public function set_from_address() {
        $from_address = $this->get_from_address();

        if ( ! $from_address )
            return null;

        // Set the from address.
        return array(
            'streetAddress' => (string) $from_address['address'],
            'city'          => (string) $from_address['city'],
            'state'         => (string) $from_address['state'],
            'postalCode'    => (string) $from_address['postcode'],
            'country'       => (string) $from_address['country'],
        );
    }

    /**
     * Get To Address
     * 
     * @return array - The to address.
     * 
     * @since   1.0.0
     */
    public function get_to_address() {
        $to_address = array();
        // Get WC "Calculate Tax Based On" setting.
        $type_of_address_to_get = get_option( 'woocommerce_tax_based_on', 'billing' );
        if ( $type_of_address_to_get !== 'base' ) :
            $customer_address = $this->get_customer_address( $type_of_address_to_get );
            $to_address       = $customer_address;
        else:
            $store_address = $this->get_store_address();
            $to_address    = $store_address;
        endif;

        return $to_address;
    }

    /**
     * Set To Address
     * 
     * @param array - $customer_address The customer's address.
     * 
     * @return mixed - The to address.
     * 
     * @since   1.0.0
     */
    public function set_to_address() {

        // Set the to address.
        $to_address = $this->get_to_address();

        if ( ! $to_address )
            return null;

        $result = array(
            'streetAddress' => (string) $to_address['address'],
            'city'          => (string) $to_address['city'],
            'state'         => (string) $to_address['state'],
            'postalCode'    => (string) $to_address['postcode'],
            'country'       => (string) $to_address['country']
        );

        // Set the to address.
        return $result;
    }

    /**
     * Is Invalid Address Field
     * 
     * @param array - $address The address to validate.
     * @param string - $field The field to validate.
     * 
     * @return bool - True if the address field is invalid, false otherwise.
     * 
     * @since   1.0.0
     */
    public function is_invalid_address_field( $address, $field ) {
        // Check if the address field is set.
        if ( ! isset( $address[$field] ) )
            return true;

        // Check if the address field is empty.
        if ( empty( $address[$field] ) )
            return true;

        // Check if the address field is not a string.
        if ( ! is_string( $address[$field] ) )
            return true;

        return false;
    }

    /**
     * Validate Address
     * 
     * @param array - $address The address to validate.
     * 
     * @return bool - True if the address is valid, false otherwise.
     * 
     * @since   1.0.0
     */
    public function validate_address( $address ) {
        // Check if the address is set.
        if (
            ! $address ||
            ! is_array( $address ) ||
            empty( $address )
        ) :
            return false;
        endif;

        $invalid_street_address = $this->is_invalid_address_field( $address, 'streetAddress' );
        $invalid_city           = $this->is_invalid_address_field( $address, 'city' );
        $invalid_state          = $this->is_invalid_address_field( $address, 'state' );
        $invalid_postal_code    = $this->is_invalid_address_field( $address, 'postalCode' );
        $invalid_country        = $this->is_invalid_address_field( $address, 'country' );

        // Check if the address has the required fields.
        if (
            $invalid_street_address ||
            $invalid_city ||
            $invalid_state ||
            $invalid_postal_code ||
            $invalid_country
        ) :
            return false;
        endif;

        return true;
    }

    /**
     * Set Tax Service Addresses
     * 
     * @param \App\TaxService - $tax_service The tax service.
     * 
     * @return mixed array|bool - The addresses if they are valid, false otherwise.
     * 
     * @since   1.0.0
     */
    public function set_tax_service_addresses( $tax_service ) {
        $addresses = array();

        $addresses['from_address'] = $this->set_from_address();
        $addresses['to_address']   = $this->set_to_address();

        $valid_from_address = $this->validate_address( $addresses['from_address'] );
        $valid_to_address   = $this->validate_address( $addresses['to_address'] );

        if ( ! $valid_from_address || ! $valid_to_address ) :
            // Trigger WC Error Notice ONCE
            $message = 'The from and to addresses are required to calculate the tax.';

            // if ( WC()->session && ! wc_has_notice( $message, 'notice' ) )
            //     wc_add_notice( __( $message, WOO_SOVOS_DOMAIN ), 'notice' );
            debug_log( $message, 'invalid from/to address ' . __CLASS__ . '->' . __FUNCTION__ );
            return false;
        endif;

        // Set the from and to address for an accurate calculation
        $tax_service->setFromAddress( $addresses['from_address'] )->setToAddress( $addresses['to_address'] );

        return $addresses;
    }

    /**
     * Get Order ID
     * 
     * @return mixed string|bool - The order ID if it is set, false otherwise.
     * 
     * @since   1.0.0
     */
    public function get_order_id() {
        $session = $this->get_wc_session();

        if ( ! $session )
            return false;

        // Get the order ID.
        $order_id = $session->get( 'order_awaiting_payment' );

        // Check if the order ID is set.
        if ( ! $order_id )
            $order_id = uniqid();

        return $order_id;
    }

    /**
     * Check if Order is Created Via Rest API
     * 
     * @param \WC_Order - The Order
     * 
     * @return bool - True if Order Created Via Rest API, False if not.
     * 
     * 
     */
    public function is_order_created_via_rest( $order ) {
        $order_created_via_api = strpos( $order->get_created_via(), 'rest-api' ) !== false;
        return $order_created_via_api;
    }

    /**
     * Set Calculated Tax
     * 
     * @param array - $cart_item The cart item.
     * @param array - $response The response from the tax service.
     * @param int - $index The index of the cart item.
     * 
     * @return string - The calculated tax from the response.
     * 
     * @since   1.0.0
     */
    public function set_calculated_tax( $cart_item, $response, $index ) {
        return $response['data']['lnRslts'][$index]['txAmt'];
    }

    /**
     * Set Total Calculated Tax
     * 
     * @param array - $response The response from the tax service.
     * 
     * @return string - The total calculated tax from the response.
     * 
     * @since   1.0.0
     */
    public function get_total_calculated_tax( $response ) {
        return $response['data']['txAmt'];
    }

    /**
     * Get Properties of Cart Item
     * 
     * @param array - $cart_item The cart item.
     * 
     * @return array - The properties of the cart item.
     * 
     * @since   1.0.0
     */
    public function get_properties_of_cart_item( $cart_item ) {
        $properties = [];
        if ( ! $cart_item || ! isset( $cart_item['data'] ) )
            return $properties;

        // Add the filter
        add_filter( 'woocommerce_product_get_tax_class', [ $this, 'change_tax_class_for_exempt_users' ], 10, 1 );

        $properties = [
            'tax_class' => $cart_item['data']->get_tax_class(),
            'total'     => $cart_item['line_total'] ? : $cart_item['data']->get_price(),
            'quantity'  => $cart_item['quantity']
        ];

        // Remove the filter
        remove_filter('woocommerce_product_get_tax_class', [ $this, 'change_tax_class_for_exempt_users' ], 10);

        return $properties;
    }

    /**
     * Get Properties of Order Item
     * 
     * @param \WC_Order_Item - $order_item The order item.
     * 
     * @return array - The properties of the order item.
     * 
     * @since   1.0.0
     */
    public function get_properties_of_order_item( $order_item ) {
        return [
            'tax_class' => $order_item->get_tax_class(),
            'total'     => $order_item->get_total(),
            'quantity'  => $order_item->get_quantity()
        ];
    }

    /**
     * Set Line Item Properties on Tax Service Class
     * 
     * @param array - $line_items The cart or order items.
     * 
     * @return void
     * 
     * @since   1.0.0
     */
    public function set_tax_service_line_item_properties( $line_items ) {
        // Set the properties of each cart item.
        foreach ( $line_items as $line_item ) :
            // If Line Item is an Array it is a Cart Item and handle accordingly
            if ( is_array( $line_item ) ) :
                $properties = $this->get_properties_of_cart_item( $line_item );
            else : // is a WC_Order_Item_Product Object
                $properties = $this->get_properties_of_order_item( $line_item );
            endif;
            $tax_class = $properties['tax_class'];
            $total     = $properties['total'];
            $quantity  = $properties['quantity'];
            $tax_service = $this->get_tax_service();
            $tax_service->setItemProperties( $tax_class, $total, $quantity );
        endforeach;
    }

    /**
     * Set Customer ID on Tax Service Class
     * 
     * @return void
     * 
     * @since 1.0.0
     */
    public function set_tax_service_customer() {
        $customer_id = get_current_user_id();

        if ( $customer_id === 0 )
            return;

        $tax_service = $this->get_tax_service();
        $tax_service->setCustomer( $customer_id );
    }

    /**
     * Prepare Tax Service
     * 
     * @param array - $line_items The cart or order items.
     * 
     * @return mixed \App\TaxService|bool - The tax service object if it is valid, false otherwise.
     * 
     * @since   1.0.0
     */
    public function prepare_tax_service( $line_items ) {
        $tax_service = $this->get_tax_service();

        $tax_service_addresses_set = $this->set_tax_service_addresses( $tax_service );

        if ( ! $tax_service_addresses_set )
            return false;

        $this->set_tax_service_customer();

        // Set the flag to use the original tax class
        $session = $this->get_wc_session();
        if ( $session )
            $session->set( 'use_original_tax_class', true );

        $this->set_tax_service_line_item_properties( $line_items );

        if ( $session )
            // Unset the flag after setting the line item properties
            $session->set( 'use_original_tax_class', false );

        return $tax_service;
    }

    /**
     * Get Unique ID from Response
     * 
     * @param array $response - The response from the tax service.
     * 
     * @return string - The unique ID from the response.
     * 
     * @since   1.2.0
     */
    public function get_unique_id_from_response( $response ) {
        if ( ! is_array( $response ) || ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
            return null;
        }

        if ( ! empty( $response['data']['txwTrnDocId'] ) ) {
            return $response['data']['txwTrnDocId'];
        }

        return isset( $response['data']['trnDocNum'] ) ? $response['data']['trnDocNum'] : null;
    }

    /**
     * Log Response to WooCommerce Logger
     * 
     * @param array - $data The data to log.
     * 
     * @return void
     */
    public function log_response( $data ) {
        $response = $data['response'];
        $type     = isset( $data['type'] ) ?
            $data['type'] :
            'quote';
        $order_id = isset( $data['order_id'] ) ?
            $data['order_id'] :
            '';

        if ( $response['success'] ) :
            $unique_id = $this->get_unique_id_from_response( $response );
        else :
            $unique_id = '';
        endif;
        $tag = ucfirst( $type ) . ' ' . $unique_id;
        $log = array(
            'Current User ID' => get_current_user_id(),
            'Order ID'           => $order_id,
            'Response'        => $response
        );

        // Get a logger instance
        $logger = wc_get_logger();
        $logger->info( "[$tag]:\n" . print_r( $log, true ) . "\n[/$tag]", array( 'source' => 'sovos' ) );
    }

    /**
     * Store Response in Session
     * 
     * @param array - $response The response from the tax service.
     * 
     * @return void
     * 
     * @since   1.1.0
     */
    public function store_response_in_session( $response ) {

        // Get the WooCommerce session
        if ( ! $this->is_valid_quote_response( $response ) ) {
            return;
        }

        $session = $this->get_wc_session();
        if ( ! $session )
            return;

        $session->set( 'sovos_tax_response', $response );
    }

    /**
     * Retrieve the cached quote from the Woo session.
     */
    protected function get_cached_quote_from_session( $line_items = null ) {
        $session  = $this->get_wc_session();
        if ( ! $session ) {
            return false;
        }

        if ( is_array( $line_items ) ) {
            $cache_key = $this->generate_cache_key( $line_items );
            $response  = $session->get( "sovos_quote_$cache_key" );

            return $this->is_valid_quote_response( $response ) ? $response : false;
        }

        $response = $session->get( 'sovos_tax_response' );

        return $this->is_valid_quote_response( $response ) ? $response : false;
    }

    /**
     * Retrieve a persisted quote from the order meta.
     */
    protected function get_cached_quote_from_order( $order ) {
        if ( ! $order || ! $order instanceof \WC_Order )
            return false;

        $response = $order->get_meta( '_sovos_tax_response', true );

        return $this->is_valid_quote_response( $response ) ? $response : false;
    }

    /**
     * Persist a successful quote onto the order for re-use.
     */
    protected function persist_quote_on_order( $order, $response ): void {
        if ( ! $order || ! $order instanceof \WC_Order )
            return;

        if ( ! $this->is_valid_quote_response( $response ) )
            return;

        $order->update_meta_data( '_sovos_tax_response', $response );
        $order->save_meta_data();
    }

    /**
     * Shared access point for a Sovos quote, preferring cached data.
     */
    protected function get_or_create_shared_quote( $line_items, $order = null ) {
        if ( $this->is_exempt_via_session_or_order( $order ) )
            return false;

        $response = $this->get_cached_quote_from_order( $order );

        if ( ! $response )
            $response = $this->get_cached_quote_from_session( $line_items );

        if ( ! $response )
            $response = $this->quote_tax( $line_items );

        return $this->is_valid_quote_response( $response ) ? $response : false;
    }

    /**
     * Validate the structure of a Sovos quote response.
     */
    protected function is_valid_quote_response( $response ): bool {
        return is_array( $response ) && ! empty( $response['success'] ) && isset( $response['data'] );
    }

    /**
     * Quote (estimate) tax for the current cart / order lines.
     *
     * Adds a simple Woo‑session cache so we don’t hammer Sovos with
     * identical requests during the same checkout visit.
     *
     * @param array $line_items Cart or order items.
     * @return array|bool Sovos response array on success, false on failure.
     */
    public function quote_tax( $line_items ) {
        static $runtime_locks = [];

        if ( $this->is_exempt_via_session_or_order() )
            return false;

        /* ────────────────────────────────
        *  NEW: session‑level cache check
        * ──────────────────────────────── */
        $cache_key = $this->generate_cache_key( $line_items ); // 32‑char MD5
        $lock_key  = $this->get_quote_lock_key( $cache_key );
        $cached    = $this->get_cached_quote( $cache_key );

        // If we’ve already quoted this exact cart + address combo,
        // short‑circuit and hand back the stored response.
        if ( $cached ) {
            return $cached;
        }

        // If another request (or another handler in the same request) is already working on this cache key, avoid a duplicate outbound call.
        if ( isset( $runtime_locks[ $lock_key ] ) || $this->has_active_quote_lock( $lock_key ) ) {
            $cached = $this->wait_for_cached_quote( $cache_key );
            return $cached ? $cached : false;
        }

        // Acquire the lock for this key.
        $runtime_locks[ $lock_key ] = true;
        if ( ! $this->acquire_quote_lock( $lock_key ) ) {
            $cached = $this->wait_for_cached_quote( $cache_key );
            return $cached ? $cached : false;
        }

        /* ────────────────────────────────
        *  ORIGINAL logic starts here
        * ──────────────────────────────── */
        $tax_service = $this->prepare_tax_service( $line_items );
        if ( ! $tax_service ) {
            $this->clear_quote_lock( $lock_key );
            unset( $runtime_locks[ $lock_key ] );
            return false;                           // prerequisites not met
        }

        try {
            $response = $tax_service->quoteTax();       // live Sovos call
        } catch ( \Throwable $e ) {
            $this->clear_quote_lock( $lock_key );
            unset( $runtime_locks[ $lock_key ] );
            throw $e;
        } finally {
            $tax_service->clearTaxService();            // tidy up
        }

        // Existing diagnostics / storage
        $this->log_response( [ 'response' => $response ] );
        $this->store_response_in_session( $response );

        /* ────────────────────────────────
        *  NEW: cache the fresh response
        * ──────────────────────────────── */
        $this->set_cached_quote( $cache_key, $response );
        $this->clear_quote_lock( $lock_key );
        unset( $runtime_locks[ $lock_key ] );

        return $response;
    }


    /**
     * Calculate Tax
     * 
     * @param array - $line_items The cart or order items.
     * 
     * @return mixed array|bool - The response from the tax service if it is valid, false otherwise.
     * 
     * @since   1.0.0
     */
    public function calculate_tax( $line_items, $order_id ) {
        $tax_service = $this->prepare_tax_service( $line_items );

        if ( ! $tax_service )
            return false;

        /**
         * Filter the order ID prefix
         * 
         * @param string - $order_id The order ID.
         * 
         * @return string - The order ID prefix.
         * 
         * @since   1.1.2
         * 
         * @hook sovos_order_id_prefix
         */
        // DISABLED until further notice - 07-18-2024
        // $prefixed_order_id = apply_filters( 'sovos_order_id_prefix', $order_id );
        // $response          = $tax_service->calculateTax( $prefixed_order_id );

        // calculateTax is quoteTax until further notice.
        $response = $tax_service->quoteTax();

        // Clear the TaxService to Prevent Duplicates
        $tax_service->clearTaxService();

        return $response;
    }


    /**
     * Create API Orders Tax Class
     * 
     * @since   1.0.0
     * 
     * @return void
     * 
     * @hooked init
     */
    public function create_api_orders_tax_class() {
        $tax_classes = \WC_Tax::get_tax_classes();
        if ( ! in_array( $this->api_tax_class_name, $tax_classes ) )
            \WC_Tax::create_tax_class( $this->api_tax_class_name, $this->api_tax_class_slug );
    }

    /**
     * Hide API Orders Tax Class from Tax Options
     * 
     * @param array - $sections The tax sections.
     * 
     * @return array - The tax sections.
     * 
     * @since   1.0.0
     * 
     * @hooked woocommerce_get_sections_tax
     */
    public function hide_api_orders_tax_class_from_tax_options( $sections ) {
        // Remove 'API Orders' from the sections
        unset( $sections[$this->api_tax_class_slug] );

        return $sections;
    }

    /**
     * Hide API Orders Tax Class from Additional Tax Classes
     * 
     * @param array - $settings The tax settings.
     * 
     * @return array - The tax settings.
     * 
     * @since   1.0.0
     * 
     * @hooked woocommerce_tax_settings
     */
    public function hide_api_orders_tax_class_from_additional_tax_classes( $settings ) {
        foreach ( $settings as &$setting ) :
            if (
                ! isset( $setting['id'] ) ||
                $setting['id'] !== 'woocommerce_tax_classes'
            )
                continue;

            // Convert the string to an array
            $tax_classes = explode( "\n", $setting['value'] );

            // Remove 'API Orders' from the array
            $tax_classes = array_filter( $tax_classes, function( $class ) {
                return trim( $class ) !== $this->api_tax_class_name;
            });

            // Convert the array back to a string
            $setting['value'] = implode( "\n", $tax_classes );

            break;

        endforeach;

        return $settings;
    }

    /**
     * Ensure API Tax Class Remains Empty
     * 
     * @param int - $tax_rate_id The tax rate ID.
     * @param array - $tax_rate_data The tax rate data.
     * 
     * @return void
     * 
     * @since   1.0.0
     * 
     * @hooked woocommerce_tax_rate_added
     */
    public function ensure_api_tax_class_remains_empty( $tax_rate_id, $tax_rate_data ) {
        if ( $tax_rate_data['tax_rate_class'] === $this->api_tax_class_slug ) :
            global $wpdb;
            $table = $wpdb->prefix . 'woocommerce_tax_rates';
            $where = ['tax_rate_id' => $tax_rate_id];
            $wpdb->delete( $table, $where );
        endif;
    }


    /**
     * Prevent Taxes from being applied to Orders Created VIA Rest API
     * 
     * @param string - $tax_class The tax class of the product.
     * 
     * @return string - The tax class of the product.
     * 
     * @since 1.0.0
     */
    public function prevent_tax_on_rest_api_orders( $tax_class ) {

        // Check if the current request is a REST API request
        if ( defined('REST_REQUEST') && REST_REQUEST )
            $tax_class = $this->api_tax_class_slug;

        return $tax_class;
    }

    /**
     * Change Tax Class for Exempt Users
     * 
     * @param string - $tax_class The tax class.
     * 
     * @return string - The tax class.
     * 
     * @since   1.1.0
     */
    public function change_tax_class_for_exempt_users( $tax_class ) {
        // Get the WooCommerce session
        $session = $this->get_wc_session();

        if ( ! $session )
            return $tax_class;

        // Store the original tax class in the session before making the request
        if ( ! $session->get( 'original_tax_class' ) )
            $session->set( 'original_tax_class', $tax_class );

        // Get the response from the session
        $response      = $session->get( 'sovos_tax_response' );
        $is_tax_exempt = $this->are_any_line_results_exempt( $response );

        // Check if the user is tax exempt
        if (
            $is_tax_exempt &&
            ! $session->get( 'use_original_tax_class' )
        ) :
            $tax_class = 'Zero Rate';
        else :
            // Retrieve the original tax class from the session after the response is received
            $tax_class = $session->get( 'original_tax_class' );
        endif;

        return $tax_class;
    }



    /**
     * WooCommerce Product Get Tax Class
     * 
     * @param string - $tax_class The tax class.
     * @param \WC_Product - $product The product.
     * 
     * @return string - The tax class.
     * 
     * @since   1.1.0
     * 
     * @hooked woocommerce_product_get_tax_class
     */
    public function woocommerce_product_get_tax_class( $tax_class, $product ) {

        // Change Tax Class For Exempt Users
        $tax_class = $this->change_tax_class_for_exempt_users( $tax_class );

        // Prevent Taxes from being applied to API Orders.
        $tax_class = $this->prevent_tax_on_rest_api_orders( $tax_class );

        return $tax_class;
    }

    /**
     * Get Tax Class by Response
     * 
     * @param array - $response The response from the tax service.
     * 
     * @return string - The tax class.
     * 
     * @since   1.2.0
     */
    public function get_tax_class_by_response( $response ) {
        $tax_class = false;
        if (
            ! isset( $response['request']['lines'] ) ||
            ! is_array( $response['request']['lines'] ) ||
            empty ( $response['request']['lines'] )
        )
            return $tax_class;

        foreach ( $response['request']['lines'] as $line ) :
            if ( ! isset( $line['goodSrvCd'] ) )
                continue;

            $tax_class = $line['goodSrvCd'];
            if ( $tax_class )
                break;
        endforeach;

        return $tax_class;
    }

    /**
     * Get Tax Class by Line Items
     * 
     * @param array - $line_items The cart or order items.
     * 
     * @return string - The tax class.
     * 
     * @since   1.2.0
     */
    public function get_tax_class_by_line_items( $line_items ) {
        $tax_class = false;

        if (
            ! $line_items ||
            empty( $line_items )
        )
            return $tax_class;

        foreach( $line_items as $line_item ) :
            $tax_class = $line_item->get_tax_class();
            if ( $tax_class )
                break;
        endforeach;

        return $tax_class;
    }

    /**
     * Construct Sovos Tax Order Data
     * 
     * @param array - $response The response from the tax service.
     * @param array - $line_items The cart or order items.
     * 
     * @return array|bool - The Sovos tax order data if it is valid, false otherwise.
     * 
     * @since   1.2.0
     */
    public function construct_sovos_tax_order_data( $response, $line_items = null, $order = null ) {
        $sovos_tax = false;

        if ( $this->is_exempt_via_session_or_order( $order ) )
            return [ 'exempt' => true ];

        // Pull from cached quote if none was provided.
        if ( ! $response ) {
            $response = $this->get_cached_quote_from_session( $line_items );
        }

        if ( ! $this->is_valid_quote_response( $response ) )
            return $sovos_tax;

        // Check if the response is valid
        if (
            ! $response['success'] ||
            ! isset( $response['data']['lnRslts'] )
        )
            return $sovos_tax;

        $tax_class = isset( $response['request']['lines'][0]['goodSrvCd'] ) ?
            $this->get_tax_class_by_response( $response ) :
            $this->get_tax_class_by_line_items( $line_items );

        if ( ! $tax_class )
            return $sovos_tax;

        
        $first_line_item = $response['data']['lnRslts'][0];

        // Create the tax rate
        $tax_rate = $this->create_tax_rate( $first_line_item, $tax_class );

        if ( ! $tax_rate )
            return $sovos_tax;


        // Set the _sovos_tax data
        $sovos_tax = [ 'tax_rate' => $tax_rate ];

        return $sovos_tax;

    }

    /**
     * Construct Sovos Tax Items Data
     * 
     * @param array - $response The response from the tax service.
     * @param array - $line_items The cart or order items.
     * @param string|null - $type_of_items (Optional) The type of items.
     * 
     * @return array - The Sovos tax items data.
     * 
     * @since   1.2.0
     */
    public function construct_sovos_tax_items_data($response, $line_items, $type_of_items = null)
    {
        $sovos_tax_items = [];

        // Validate response
        if (!$response['success'] || !isset($response['data']['lnRslts'])) {
            return $sovos_tax_items;
        }

        // Reindex numerically so [$index] lines up with Sovos lnRslts index
        $line_items_reindexed = array_values($line_items);

        foreach ($response['data']['lnRslts'] as $index => $sovos_line_item) {

            // Match the corresponding line item
            if (!isset($line_items_reindexed[$index])) {
                continue;
            }

            $line_item_by_index = $line_items_reindexed[$index];

            // Cart item → use cart item key; Order item → use item ID
            $line_item_key = is_array($line_item_by_index)
                ? $line_item_by_index['key']
                : $line_item_by_index->get_id();

            if (!isset($line_items[$line_item_key])) {
                continue;
            }

            $line_item = $line_items[$line_item_key];

            // Detect type once
            if ($type_of_items === null) {
                $type_of_items = is_array($line_item) ? 'cart_items' : 'order_items';
            }

            // Get tax class off the product/item
            $line_item_product = ($type_of_items === 'cart_items') ? $line_item['data'] : $line_item;
            $tax_class = $line_item_product->get_tax_class();

            // Build a Woo-compatible tax rate array (NO tax_rate_id here)
            $tax_rate = $this->create_tax_rate($sovos_line_item, $tax_class);
            if (!$tax_rate) {
                continue;
            }

            // Attach Sovos meta to the item for later use
            if ($type_of_items === 'cart_items') {
                $sovos_tax = isset($line_item['_sovos_tax']) ? $line_item['_sovos_tax'] : [];
                $sovos_tax['tax_rate'] = $tax_rate;
                $sovos_tax['_tax_quoted'] = true;
                $line_item['_sovos_tax'] = $sovos_tax; // keep in-memory for this request
            } else {
                $sovos_tax = $line_item->get_meta('_sovos_tax') ?: [];
                $sovos_tax['tax_rate'] = $tax_rate;
                $sovos_tax['_tax_quoted'] = true;
                $line_item->update_meta_data('_sovos_tax', $sovos_tax);
                $line_item->save_meta_data();
            }

            // Return the updated item in the collection
            $sovos_tax_items[$line_item_key] = $line_item;
        }

        return $sovos_tax_items;
    }

    /**
     * Add Sovos Order Notes to New Order
     * 
     * @param int - $order_id The order ID.
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_new_order
     */
    public function add_sovos_transaction_id_to_new_order( $order_id, $order ) {
        if ( $this->is_order_created_via_rest( $order ) )
            return;

        if ( $this->is_exempt_via_session_or_order( $order ) )
            return;

        // TODO: differentiate the _sovos_tax data order and item data
        // EG: _sovos_tax_order from _sovos_tax_item
        $sovos_tax = $order->get_meta( '_sovos_tax' );

        // Check if this function has already been run for this order
        if (
            isset( $sovos_tax['_sovos_order_notes_added'] ) &&
            $sovos_tax['_sovos_order_notes_added'] == true
        )
            return;

        $order_items = $order->get_items();
        $response    = $this->get_or_create_shared_quote( $order_items, $order );

        // Persist the cached quote for later hooks.
        if ( $response )
            $this->persist_quote_on_order( $order, $response );

        // Construct the _sovos_tax data
        // $this->construct_sovos_tax_items_data( $response, $order_items );
        $sovos_tax = $this->construct_sovos_tax_order_data( $response, null, $order );

        if ( ! $sovos_tax )
            return;

        // TODO: Refactor this method to use stored Sovos Tax Item Data instead of $response.
        $this->add_sovos_order_notes( $order, $response );

        // Mark this function as run for this order
        $sovos_tax['_sovos_order_notes_added'] = true;
        $order->update_meta_data( '_sovos_tax', $sovos_tax );

        // Recalculate taxes
        $order->calculate_taxes();

        // Save the order meta data
        $order->save();
    }

    /**
     * Add Sovos Transaction ID when Order status changes to Processing
     * 
     * @param int $order_id - The order ID.
     * 
     * @return void
     * 
     * @since 1.2.0
     * 
     * @hooked woocommerce_order_status_processing
     */
    public function send_order_data_to_sovos( $order_id, $old_status, $new_status, $order ) {
        if ( 'processing' !== $new_status )
            return;

        if ( $this->is_exempt_via_session_or_order( $order ) )
            return;

        $order_items = $order->get_items();
        $response    = $this->get_cached_quote_from_order( $order );

        if ( ! $response )
            $response = $this->get_cached_quote_from_session();

        // If we lack a cached txwTrnDocId, fall back to a fresh Sovos call.
        if ( ! $response || ! isset( $response['data']['txwTrnDocId'] ) )
            $response = $this->calculate_tax( $order_items, $order_id );

        // Nothing to do if we still don't have a response.
        if ( ! $response )
            return;

        // Persist the response for future hooks.
        $this->persist_quote_on_order( $order, $response );

        // Check if the response is valid.
        if ( $response['success'] && isset( $response['data']['txwTrnDocId'] ) ) :
            // Store the txwTrnDocId as order meta data.
            $order->update_meta_data( 'txwTrnDocId', $response['data']['txwTrnDocId'] );
            $order_note = __( 'SOVOS Calculate Tax Transaction Created.<br />Transaction ID: ' . $response['data']['txwTrnDocId'], WOO_SOVOS_DOMAIN );
            $order->add_order_note( $order_note );
            $order->save();
        endif;
    }

    /**
     * Rename a key in the Array
     *
     * @param array $array The array - Ideally the $response.
     * @param string $old_key The old key.
     * @param string $new_key The new key.
     *
     * @return array The modified response array.
     */
    public function rename_key_in_array( $array, $old_key, $new_key ) {
        if ( isset( $array[$old_key] ) ) :
            $array[$new_key] = $array[$old_key];
            unset( $array[$old_key] );
        endif; // endif ( isset( $array['request'][$old_key] ) ) :

        return $array;
    }

    /**
     * Rename Line Item Keys
     *
     * @param array $line_items The line items.
     *
     * @return array The modified line items.
     */
    public function rename_line_item_keys( $line_items ) {
        // Prepend 'Line Item' to Line Item Key and Increment by 1
        if ( count( $line_items ) > 0 ) :
            foreach ( $line_items as $key => $line_item ) :
                $new_key_name = is_numeric( $key ) ?
                    '#' . ( $key + 1 ) :
                    $key;
                $line_items[$new_key_name] = $line_items[$key];
                unset( $line_items[$key] );
            endforeach;
        endif; // endif ( count( $line_items ) > 0 ) :

        return $line_items;
    }

    
    /**
     * Add Sovos Order Notes
     * 
     * @param \WC_Order - $order The order.
     * @param array - $response The response from the tax service.
     * 
     * @return void
     * 
     * @since 1.2.0
     */
    public function add_sovos_order_notes( $order, $response ) {
        if ( ! $this->is_valid_quote_response( $response ) )
            return;

        $empty = true;
        ob_start();
        ?>
        <table style="border-collapse: collapse;" border="1" cellpadding="2.5em">
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
            <?php
            if( isset( $response['data']['trnDocNum'] ) ) :
                $empty = false;
                ?>
                <tr>
                    <td>Transaction Document Number</td>
                    <td><?php echo $response['data']['trnDocNum']; ?></td>
                </tr>
            <?php endif; ?>

            <?php
            if( isset( $response['data']['txAmt'] ) ) :
                $empty = false;
                ?>
                <tr>
                    <td>Tax Amount</td>
                    <td><?php echo $response['data']['txAmt']; ?></td>
                </tr>
            <?php endif; ?>

            <?php
            if( isset( $response['data']['lnRslts'][0]['jurRslts'][0]['txName'] ) ) :
                $empty = false;
                ?>
                <tr>
                    <td>Tax Name</td>
                    <td><?php echo $response['data']['lnRslts'][0]['jurRslts'][0]['txName']; ?></td>
                </tr>
            <?php endif; ?>

            <?php
            if( isset(
                $response['data']['lnRslts'][0]['jurRslts'][0]['txJurUIDCntry'],
                $response['data']['lnRslts'][0]['jurRslts'][0]['txJurUIDStatePrv']
            ) ) :
                $empty = false;
                ?>
                <tr>
                    <td>Tax Jurisdiction</td>
                    <td><?php echo "{$response['data']['lnRslts'][0]['jurRslts'][0]['txJurUIDCntry']}, {$response['data']['lnRslts'][0]['jurRslts'][0]['txJurUIDStatePrv']}"; ?></td>
                </tr>
            <?php endif; ?>

            <?php if ( $response['request'] ) : ?>
                <tr>
                    <td>Request</td>
                    <td>
                        <div class="sovos-request-tooltip sovos-order-notes-tooltip sovos-tax-tooltip">
                            Hover to View Request
                            <span class="sovos-tooltip-text">
                                <table>
                                    <?php
                                    // Rename lines key to Line Items
                                    $response['request'] = $this->rename_key_in_array( $response['request'], 'lines', 'Line Items' );

                                    // Rename the Line Items' Keys
                                    $response['request']['Line Items'] = $this->rename_line_item_keys( $response['request']['Line Items'] );

                                    unset( $response['request']['usrname'] );
                                    unset( $response['request']['pswrd'] );

                                    $this->recursive_tooltip_rows( $response['request'] );
                                    ?>
                                </table>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php
                // Remove Request from Response.
                unset( $response['request'] );
            endif;
            ?>

            <tr>
                <td>Response</td>
                <td>
                    <div class="sovos-response-tooltip sovos-order-notes-tooltip sovos-tax-tooltip">
                        Hover to View Response
                        <span class="sovos-tooltip-text">
                            <table>
                                <?php
                                $old_line_key = 'lnRslts';
                                $new_line_key = 'Line Items';
                                // Rename the lnRslts to Line Items
                                $response['data'] = $this->rename_key_in_array( $response['data'], $old_line_key, $new_line_key );

                                // Rename the Line Items' Keys 
                                $response['data'][$new_line_key] = $this->rename_line_item_keys( $response['data'][$new_line_key] );

                                if ( count( $response['data'][$new_line_key] ) ) :
                                    foreach ( $response['data'][$new_line_key] as $key => $line_item ) :
                                        $old_jur_key = 'jurRslts';
                                        $new_jur_key = 'Jurisdictions ' . $key;
                                        // Rename the jurRslts to Jurisdictions
                                        $response['data'][$new_line_key][$key] = $this->rename_key_in_array( $response['data'][$new_line_key][$key], $old_jur_key, $new_jur_key );
                                        // Rename Jurisdictions
                                        $response['data'][$new_line_key][$key][$new_jur_key] = $this->rename_line_item_keys( $response['data'][$new_line_key][$key][$new_jur_key] );
                                    endforeach;
                                endif; // endif ( count( $response['data'][$new_line_key] ) ) :

                                $this->recursive_tooltip_rows( $response );
                                ?>
                            </table>
                        </span>
                    </div>
                </td>
            </tr>

        </table>
        <?php
        $order_note = ob_get_clean();

        // Add the formatted order note to the order
        if( ! $empty ) :
            $order_note = "SOVOS Tax Info: $order_note";
            $order->add_order_note( $order_note );
        endif;
    }

    /**
     * Display Sovos Transaction ID in Admin
     * 
     * @param int - $order_id The order ID.
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_admin_order_totals_after_tax
     */
    public function display_sovos_transaction_id_in_admin( $order_id ) {
        $order                = wc_get_order( $order_id );
        $sovos_transaction_id = $order->get_meta( 'txwTrnDocId' );

        if ( ! $sovos_transaction_id )
            return;

        $tip = "SOVOS Transaction ID: $sovos_transaction_id <br /> Click to Copy ID to Clipboard";
        $toast_message = 'SOVOS Transaction ID copied to clipboard';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var $body = $('body'),
                 $tooltip = $('<span class="woocommerce-help-tip sovos-tooltip" aria-label="<?php echo $tip; ?>" data-sovos-id="<?php echo $sovos_transaction_id; ?>"></span>');
                $('.wc-order-totals tr').each(function() {
                    var label = $(this).find('.label').text();
                    if (label.indexOf('Tax:') !== -1) {
                        $(this).find('.total').addClass('tax-total').append($tooltip);
                    }
                });
                var $sovosToolTip = $('.sovos-tooltip');
                $sovosToolTip.tipTip({
                    'attribute': 'aria-label',
                    'fadeIn':    50,
                    'fadeOut':   50,
                    'delay':     200
                }).on('click', function(){
                    var $temp = $('<input>');
                    $body.append($temp);
                    $temp.val($(this).attr('data-sovos-id')).select();
                    document.execCommand('copy');
                    $temp.remove();

                    // Create toast notification
                    var $toast = $('<div class="sovos-toast"><?php echo $toast_message; ?></div>');
                    $body.append($toast);
                    setTimeout(function() {
                        $toast.fadeOut(500, function() {
                            $(this).remove();
                        });
                    }, 3000);
                });
            });
        </script>
        <?php
    }

    /**
     * Format State
     * 
     * @param array - $states The WooCommerce states array.
     * @param string - $response_state The state from the tax service response.
     * 
     * @return string - The formatted state.
     * 
     * @since 1.1.0
     */
    public function format_state( $states, $response_state ) {
        // Convert State from Full Name to Abbreviation in WC State Code
        $tax_rate_state = '';
        // Find State in WC States Array.
        // EG: $states['IN'] = 'Indiana'.
        foreach ( $states as $state_code => $state_name ) :
            if ( ! is_string( $state_name ) )
                continue;

            $formatted_state_name     = strtoupper( $state_name );
            $formatted_response_state = strtoupper( $response_state );
            if ( $formatted_state_name === $formatted_response_state ) :
                $tax_rate_state = $state_code;
                break;
            endif;
        endforeach;

        return $tax_rate_state;
    }

    /**
     * Sovos Line Item Tax Rate
     * 
     * @param array - $sovos_line_item The line item. Not to be confused with WooCommerce line items.
     * 
     * @return float - The tax rate.
     * 
     * @since 1.1.0
     */
    public function sovos_line_item_tax_rate( $sovos_line_item ) {
        $tax_rate = 0;

        if (
            isset( $sovos_line_item['txAmt'] ) &&
            is_numeric( $sovos_line_item['txAmt'] ) &&
            isset( $sovos_line_item['grossAmt'] ) &&
            is_numeric( $sovos_line_item['grossAmt'] )
        ) :
            $tax_amount   = $sovos_line_item['txAmt'];
            $gross_amount = $sovos_line_item['grossAmt'];
            $tax_rate     = round( $tax_amount / $gross_amount * 100, 2 ); // The tax rate in WooCommerce is stored as a percentage.
        endif;

        return $tax_rate;
    }

    /**
     * Create Tax Rate
     * 
     * @param array - $sovos_line_item The line item. Not to be confused with WooCommerce line items.
     * @param string - $tax_class The tax class of the product.
     * 
     * @return array|bool - The tax rate if it is valid, false otherwise.
     * 
     * @since 1.0.0
     */
    public function create_tax_rate( $sovos_line_item, $tax_class ) {
        if ( ! isset( $sovos_line_item['jurRslts'][0] ) )
            return false;

        $jurResult             = $sovos_line_item['jurRslts'][0];
        $wc_states_for_country = WC()->countries->get_states( $jurResult['txJurUIDCntryISO'] );

        $tax_rate_state = $this->format_state( $wc_states_for_country, $jurResult['txJurUIDStatePrv'] );

        $calculated_tax_rate = $this->sovos_line_item_tax_rate( $sovos_line_item );

        // Create a tax rate.
        $tax_rate = array(
            'tax_rate_country'  => $jurResult['txJurUIDCntryISO'],
            'tax_rate_state'    => $tax_rate_state,
            'tax_rate'          => $calculated_tax_rate,
            'tax_rate_name'     => $jurResult['txName'],
            'tax_rate_priority' => 1,
            'tax_rate_compound' => false,
            'tax_rate_shipping' => false,
            'tax_rate_order'    => 1,
            'tax_rate_class'    => $tax_class
        );

        return $tax_rate;
    }

    /**
     * Get Existing Tax Rate
     * 
     * @param array - $tax_rate The tax rate.
     * 
     * @return 
     * 
     * @since 1.0.0
     */
    public function get_existing_tax_rate( $tax_rate ) {
        global $wpdb;
        $tax_rate_id = null;

        // Prepare the SQL query to get the tax rate ID.
        $sql = $wpdb->prepare(
            "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates
            WHERE tax_rate_country = %s
            AND tax_rate_state = %s
            AND tax_rate_name = %s
            AND tax_rate = %f
            AND tax_rate_priority = %d
            AND tax_rate_compound = %d
            AND tax_rate_shipping = %d
            AND tax_rate_order = %d
            AND tax_rate_class = %s",
            $tax_rate['tax_rate_country'],
            $tax_rate['tax_rate_state'],
            $tax_rate['tax_rate_name'],
            $tax_rate['tax_rate'],
            $tax_rate['tax_rate_priority'],
            $tax_rate['tax_rate_compound'],
            $tax_rate['tax_rate_shipping'],
            $tax_rate['tax_rate_order'],
            $tax_rate['tax_rate_class']
        );

        if ( ! $sql )
            return $tax_rate_id;

        $query_result = $wpdb->get_var( $sql );

        if ( $query_result )
            $tax_rate_id = intval( $query_result );

        return $tax_rate_id;
    }

    /**
     * Insert Tax Rate (robust across WC versions)
     *
     * @param array $tax_rate
     * @return int New or existing tax_rate_id
     */
    public function insert_tax_rate( $tax_rate ) {
        // Never accept a caller-provided primary key
        if ( isset( $tax_rate['tax_rate_id'] ) ) {
            unset( $tax_rate['tax_rate_id'] );
        }

        // 🔒 Normalize so identical jurisdictions reuse ONE row
        $tax_rate['tax_rate_class'] = '';                // Standard class only
        $tax_rate['tax_rate_name']  = 'Sovos Combined';  // <- constant label, not per-jurisdiction

        // Provide safe defaults
        if ( ! isset( $tax_rate['tax_rate_postcode'] ) ) $tax_rate['tax_rate_postcode'] = '';
        if ( ! isset( $tax_rate['tax_rate_city'] ) )     $tax_rate['tax_rate_city']     = '';
        if ( ! isset( $tax_rate['tax_rate_priority'] ) ) $tax_rate['tax_rate_priority'] = 1;
        if ( ! isset( $tax_rate['tax_rate_order'] ) )    $tax_rate['tax_rate_order']    = 1;

        // Normalize types
        $tax_rate['tax_rate']          = isset( $tax_rate['tax_rate'] ) ? (float) $tax_rate['tax_rate'] : 0.0;
        $tax_rate['tax_rate_compound'] = empty( $tax_rate['tax_rate_compound'] ) ? 0 : 1;
        $tax_rate['tax_rate_shipping'] = empty( $tax_rate['tax_rate_shipping'] ) ? 0 : 1;

        // Reuse identical rate if it already exists
        $existing_tax_rate_id = $this->get_existing_tax_rate( $tax_rate ); // you can keep your current query
        if ( $existing_tax_rate_id ) {
            return (int) $existing_tax_rate_id;
        }

        global $wpdb;
        $table_name  = $wpdb->prefix . 'woocommerce_tax_rates';
        $columns     = $this->get_tax_rate_table_columns( $table_name );
        $has_legacy_schema = ! in_array( 'tax_rate_postcode', $columns, true ) || ! in_array( 'tax_rate_city', $columns, true );

        // Prefer Woo helper if the schema supports the expected columns
        if ( ! $has_legacy_schema && method_exists( '\WC_Tax', '_insert_tax_rate' ) ) {
            return (int) \WC_Tax::_insert_tax_rate( $tax_rate );
        }

        // Legacy / custom schemas: build an insert using only available columns.
        $row     = [];
        $formats = [];

        $row['tax_rate_country']  = strtoupper( (string) ( $tax_rate['tax_rate_country'] ?? '' ) );
        $formats[] = '%s';

        if ( in_array( 'tax_rate_state', $columns, true ) ) {
            $row['tax_rate_state'] = strtoupper( (string) ( $tax_rate['tax_rate_state'] ?? '' ) );
            $formats[] = '%s';
        }

        $row['tax_rate']          = wc_format_decimal( (float) ( $tax_rate['tax_rate'] ?? 0 ), 4 );
        $formats[] = '%s';

        $row['tax_rate_name']     = substr( (string) ( $tax_rate['tax_rate_name'] ?? 'Sovos Combined' ), 0, 200 );
        $formats[] = '%s';

        $row['tax_rate_priority'] = (int) ( $tax_rate['tax_rate_priority'] ?? 1 );
        $formats[] = '%d';

        $row['tax_rate_compound'] = (int) ( $tax_rate['tax_rate_compound'] ?? 0 );
        $formats[] = '%d';

        $row['tax_rate_shipping'] = (int) ( $tax_rate['tax_rate_shipping'] ?? 0 );
        $formats[] = '%d';

        if ( in_array( 'tax_rate_order', $columns, true ) ) {
            $row['tax_rate_order'] = (int) ( $tax_rate['tax_rate_order'] ?? 1 );
            $formats[] = '%d';
        }

        $row['tax_rate_class']    = (string) ( $tax_rate['tax_rate_class'] ?? '' );
        $formats[] = '%s';

        if ( in_array( 'tax_rate_postcode', $columns, true ) ) {
            $row['tax_rate_postcode'] = (string) ( $tax_rate['tax_rate_postcode'] ?? '' );
            $formats[] = '%s';
        }

        if ( in_array( 'tax_rate_city', $columns, true ) ) {
            $row['tax_rate_city'] = (string) ( $tax_rate['tax_rate_city'] ?? '' );
            $formats[] = '%s';
        }

        $wpdb->insert( $table_name, $row, $formats );
        $new_id = (int) $wpdb->insert_id;
        if ( $new_id ) {
            do_action( 'woocommerce_tax_rate_added', $new_id, $tax_rate );
        }

        return $new_id;
    }

    /**
     * Describe the schema for the Woo tax rates table (cached).
     */
    protected function get_tax_rate_table_columns( $table_name ) {
        static $columns_cache = [];

        if ( isset( $columns_cache[ $table_name ] ) ) {
            return $columns_cache[ $table_name ];
        }

        global $wpdb;
        $columns = [];
        $results = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );

        if ( is_array( $results ) ) {
            foreach ( $results as $column ) {
                if ( isset( $column->Field ) ) {
                    $columns[] = $column->Field;
                }
            }
        }

        $columns_cache[ $table_name ] = $columns;

        return $columns;
    }

    /**
     * Determine if a product (or its parent) is marked as always exempt.
     */
    protected function is_product_marked_always_exempt( $product ) : bool {
        if ( ! $product || ! $product instanceof \WC_Product )
            return false;

        $flag = wc_string_to_bool( $product->get_meta( '_sovos_always_exempt', true ) );

        if ( ! $flag && $product->get_parent_id() ) {
            $parent = wc_get_product( $product->get_parent_id() );
            $flag   = $parent ? wc_string_to_bool( $parent->get_meta( '_sovos_always_exempt', true ) ) : false;
        }

        return $flag;
    }

    /**
     * Extract product exemption flags for each line item.
     */
    protected function get_product_exemption_flags( $line_items ) : array {
        $flags = [];

        foreach ( $line_items as $line_item ) {
            $product = null;

            if ( is_array( $line_item ) && isset( $line_item['data'] ) ) {
                $product = $line_item['data'];
            } elseif ( is_object( $line_item ) && method_exists( $line_item, 'get_product' ) ) {
                $product = $line_item->get_product();
            }

            $flags[] = $this->is_product_marked_always_exempt( $product );
        }

        return $flags;
    }

    /**
     * Parse exemption entries from meta values.
     */
    protected function parse_exemption_entries( $value ) : array {
        $entries = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
        $entries = array_map( 'trim', $entries );
        $entries = array_filter( $entries );

        return array_values( $entries );
    }

    /**
     * Gather exemption allowlists from user meta.
     */
    protected function gather_user_exemption_allowlists( $user_id ) : array {
        if ( ! $user_id )
            return [ 'primary' => [], 'alternate' => [], 'combined' => [] ];

        $primary   = $this->parse_exemption_entries( get_user_meta( $user_id, '_sovos_exempt_emails', true ) );
        $alternate = $this->parse_exemption_entries( get_user_meta( $user_id, '_sovos_exempt_alternate_emails', true ) );

        return [
            'primary'   => $primary,
            'alternate' => $alternate,
            'combined'  => array_values( array_unique( array_merge( $primary, $alternate ) ) ),
        ];
    }

    /**
     * Determine if an email matches a list of allowlisted emails/domains.
     */
    protected function email_matches_allowlist( $email, array $entries ) : bool {
        if ( ! $email )
            return false;

        $email  = strtolower( $email );
        $domain = substr( strrchr( $email, '@' ), 1 );

        foreach ( $entries as $entry ) {
            $entry = strtolower( trim( $entry ) );

            if ( ! $entry )
                continue;

            // Exact email match
            if ( strpos( $entry, '@' ) !== false && strpos( $entry, '@' ) !== 0 ) {
                if ( $email === $entry )
                    return true;

                $entry_domain = substr( strrchr( $entry, '@' ), 1 );
                if ( $entry_domain && $domain === $entry_domain )
                    return true;

                continue;
            }

            // Domain match (with or without @ prefix)
            $entry_domain = ltrim( $entry, '@' );
            if ( $entry_domain && $domain === $entry_domain )
                return true;
        }

        return false;
    }

    /**
     * Resolve the checkout email from order/session/request context.
     */
    protected function get_checkout_email( $order = null ) : string {
        if ( $order && method_exists( $order, 'get_billing_email' ) ) {
            $order_email = $order->get_billing_email();
            if ( $order_email )
                return sanitize_email( $order_email );
        }

        if ( isset( $_POST['billing_email'] ) )
            return sanitize_email( wp_unslash( $_POST['billing_email'] ) );

        if ( function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_billing_email' ) ) {
            $customer_email = WC()->customer->get_billing_email();
            if ( $customer_email )
                return sanitize_email( $customer_email );
        }

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( $user && isset( $user->user_email ) )
                return sanitize_email( $user->user_email );
        }

        return '';
    }

    /**
     * Build a snapshot of exemption markers for the current context.
     */
    protected function collect_exemption_context( $line_items, $order = null ) : array {
        $email         = $this->get_checkout_email( $order );
        $product_flags = $this->get_product_exemption_flags( $line_items );

        $user_id = 0;
        if ( $order && method_exists( $order, 'get_user_id' ) )
            $user_id = $order->get_user_id();
        elseif ( is_user_logged_in() )
            $user_id = get_current_user_id();

        if ( ! $user_id && $email ) {
            $user = get_user_by( 'email', $email );
            if ( $user )
                $user_id = $user->ID;
        }

        $allowlists = $this->gather_user_exemption_allowlists( $user_id );
        $vat_exempt = false;

        if ( $order && method_exists( $order, 'is_vat_exempt' ) ) {
            $vat_exempt = (bool) $order->is_vat_exempt();
        } elseif ( function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_is_vat_exempt' ) ) {
            $vat_exempt = (bool) WC()->customer->get_is_vat_exempt();
        }

        $email_exempt = $this->email_matches_allowlist( $email, $allowlists['combined'] );

        return [
            'email'               => $email,
            'user_id'             => $user_id,
            'allowlists'          => $allowlists,
            'allowlist_hash'      => md5( wp_json_encode( [ $allowlists['primary'], $allowlists['alternate'] ] ) ),
            'email_exempt'        => $email_exempt,
            'vat_exempt'          => $vat_exempt,
            'product_flags'       => $product_flags,
            'all_products_exempt' => ! empty( $product_flags ) && count( array_filter( $product_flags ) ) === count( $product_flags ),
        ];
    }

    /**
     * Detect whether the current request should skip Sovos because it is already exempt.
     */
    protected function detect_known_exemption( $line_items, $order = null ) : array {
        $context        = $this->collect_exemption_context( $line_items, $order );
        $session        = $this->get_wc_session();
        $session_exempt = $session ? (bool) $session->get( 'sovos_is_exempt' ) : false;
        $order_exempt   = $order instanceof \WC_Order ? wc_string_to_bool( $order->get_meta( '_sovos_is_exempt', true ) ) : false;

        $is_exempt = (
            $order_exempt ||
            $session_exempt ||
            $context['all_products_exempt'] ||
            $context['vat_exempt'] ||
            $context['email_exempt']
        );

        $reason = '';
        if ( $order_exempt )
            $reason = 'order_meta';
        elseif ( $session_exempt )
            $reason = 'session_flag';
        elseif ( $context['all_products_exempt'] )
            $reason = 'product_meta';
        elseif ( $context['vat_exempt'] )
            $reason = 'customer_vat_exempt';
        elseif ( $context['email_exempt'] )
            $reason = 'customer_email_allowlist';

        $context['is_exempt']      = $is_exempt;
        $context['reason']         = $reason;
        $context['session_exempt'] = $session_exempt;
        $context['order_exempt']   = $order_exempt;

        return $context;
    }

    /**
     * Persist exemption markers to session and order metadata.
     */
    protected function sync_exemption_markers( array $context, $order = null ): void {
        $session = $this->get_wc_session();

        if ( ! empty( $context['is_exempt'] ) ) {
            if ( $session ) {
                $session->set( 'sovos_is_exempt', true );
                $session->set( 'sovos_exempt_reason', $context['reason'] );
                $session->set( 'sovos_exempt_email', $context['email'] );
            }

            if ( $order && $order instanceof \WC_Order ) {
                $order->update_meta_data( '_sovos_is_exempt', true );

                if ( ! empty( $context['reason'] ) )
                    $order->update_meta_data( '_sovos_exempt_reason', $context['reason'] );

                if ( ! empty( $context['email'] ) )
                    $order->update_meta_data( '_sovos_exempt_email', $context['email'] );

                $order->save_meta_data();
            }

            return;
        }

        if ( $session ) {
            $session->set( 'sovos_is_exempt', false );
            $session->set( 'sovos_exempt_reason', null );
            $session->set( 'sovos_exempt_email', null );
        }
    }

    /**
     * Check whether the current session/order is flagged as exempt.
     */
    protected function is_exempt_via_session_or_order( $order = null ): bool {
        $session = $this->get_wc_session();

        if ( $session && $session->get( 'sovos_is_exempt' ) )
            return true;

        if ( $order && $order instanceof \WC_Order )
            return wc_string_to_bool( $order->get_meta( '_sovos_is_exempt', true ) );

        return false;
    }


    /**
     * Are Any Line Results Exempt
     * 
     * @param array - $response The response from the tax service.
     * 
     * @return bool - True if any line results are exempt, false otherwise.
     * 
     * @since   1.1.0
     */
    public function are_any_line_results_exempt( $response ) {
        $are_any_line_results_exempt = false;
        if (
            isset( $response['data']['lnRslts'] ) &&
            ! empty ( $response['data']['lnRslts'] )
        ) :
            foreach ( $response['data']['lnRslts'] as $line_result ) :
                if (
                    isset( $line_result['jurRslts'] ) &&
                    ! empty ( $line_result['jurRslts'] )
                ) :
                    foreach ( $line_result['jurRslts'] as $jur_result ) :
                        if ( $jur_result['xmptAmt'] > 0 ) :
                            $are_any_line_results_exempt = true;
                            break 2; // Break both foreach loops
                        endif;
                    endforeach;
                endif;
            endforeach;
        endif;
        return $are_any_line_results_exempt;
    }

    /**
     * Is on Front End Checkout
     * 
     * @return bool - True if on front end checkout, false otherwise.
     * 
     * @since 1.2.0
     */
    function is_on_front_end_checkout() {
        $is_on_front_end_checkout = true;
        // Allow Tax Calculation on Back-End.
        if ( is_admin() && ! defined( 'DOING_AJAX' ) )
            $is_on_front_end_checkout = false;

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 )
            $is_on_front_end_checkout = false;

        // Check if it is checkout page
        if ( is_checkout() )
            $is_on_front_end_checkout = false;

        return $is_on_front_end_checkout;
    }

    /**
     * Prevent Tax Calculation Unless on Checkout
     * 
     * @param \WC_Cart - $cart The WooCommerce Cart.
     * 
     * @return void
     * 
     * @since 1.2.0
     * 
     * @hooked woocommerce_before_calculate_totals
     */
    public function prevent_tax_calulation_unless_on_checkout( $cart ) {
        if ( ! $this->is_on_front_end_checkout() )
            return;

        // Loop through cart items and set tax status to 'none'
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) :
            $product = $cart_item['data'];
            $product->set_tax_status( 'none' );
        endforeach;
    }

    /**
     * Add Tax Notice to Cart
     * 
     * @param string - $tax_total_html The tax total HTML.
     * 
     * @return string - The tax total HTML.
     * 
     * @since 1.2.0
     * 
     * @hooked woocommerce_cart_totals_taxes_total_html
     */
    public function add_tax_notice_to_cart( $tax_total_html ) {
        if ( $this->is_on_front_end_checkout() ) :
            $message = __( 'Taxes will be calculated at Checkout', WOO_SOVOS_DOMAIN );
            $markup  = "<div style=\"display:inline-block;\">$message</div>";
            $tax_total_html .= " $markup";
        endif;

        return $tax_total_html;
    }


    /**
     * Set Tax Rates
     * 
     * @param array - $line_items The cart or order items.
     * 
     * @return array - $line_items The Line Items
     * 
     * @since 1.2.0
     * 
     * 
     */
    public function set_tax_rates( $line_items, $type_of_items = null ) {

        $response = $this->quote_tax( $line_items );

        // Check if the response is valid.
        if ( $response['success'] && isset( $response['data']['lnRslts'] ) ) :
            $line_items_reindexed = array_values( $line_items ); // Reindex the array numerically
            foreach ( $response['data']['lnRslts'] as $index => $sovos_line_item ) :

                // Get Corresponding $line_item
                $line_item_by_index   = $line_items_reindexed[$index];
                $line_item_key        = $line_item_by_index['key'];
                $line_item            = $line_items[$line_item_key];
                if ( ! $line_item )
                    continue;

                if ( $type_of_items === null ) :
                    if ( is_array( $line_item ) ) :
                        $type_of_items     = 'cart_items';
                    else :
                        $type_of_items     = 'order_items';
                    endif;
                endif;

                $line_item_product = $type_of_items === 'cart_items' ?
                    $line_item['data'] :
                    $line_item;

                $tax_class = $line_item_product->get_tax_class();
                $tax_rate  = $this->create_tax_rate( $sovos_line_item, $tax_class );

                // Bail if no tax rate is created.
                if ( ! $tax_rate )
                    continue;

                // Create Unique ID for Tax Rate
                $tax_rate_id = $this->get_unique_id_from_response( $response );
                $tax_rate['tax_rate_id'] = $tax_rate_id;

                // Store the tax rate as meta data.
                $line_item['_sovos_tax'] = [ 'tax_rate' => $tax_rate ];

                // Get the tax amount.
                $tax_amount = $sovos_line_item['txAmt'];

                // Check if the tax has already been added.
                if (
                    ! isset( $line_item['_sovos_tax']['_tax_added'] ) ||
                    ! $line_item['_sovos_tax']['_tax_added']
                ) :
                    // Mark the tax as added.
                    $line_item['_sovos_tax']['_tax_added'] = true;
                endif;

                // Update the cart item.
                WC()->cart->cart_contents[ $line_item_key ] = $line_item;
            endforeach; // endforeach ( $response['data']['lnRslts'] as $index => $sovos_line_item ) :
        endif; // endif ( $response['success'] && isset( $response['data']['lnRslts'] ) ) :

        return $line_items;
    }

    /**
     * Set Tax Rates On Cart Items
     * 
     * @param \WC_Cart - $cart - the WooCommerce Cart
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_before_calculate_totals
     * 
     */
    public function set_tax_rates_on_cart_items($cart)
    {
        // No-op: we do NOT mutate cart line totals anymore.
        return;

        // // Check if the action is fired in the admin.
        // if (is_admin() && !defined('DOING_AJAX'))
        //     return;

        // if (did_action('woocommerce_before_calculate_totals') >= 2)
        //     return;

        // // Only set tax rate on checkout.
        // if (!is_checkout())
        //     return;

        // $cart_items = $cart->get_cart();
        // $cart_items = $this->set_tax_rates($cart_items);
        // $cart->calculate_totals();
    }

    /**
     * Add Custom Sovos Tax Rate Dynamically without inserting it into the Tax Rate Table
     * 
     * @param \WC_Order_Item_Tax $item - The WC Order Item Tax Object
     * @param int $tax_rate_id - The Tax Rate ID.
     * @param \WC_Order $order - The WC Order Object.
     * 
     * @return void
     * 
     * @since 1.2.0
     * 
     * @hooked woocommerce_checkout_create_order_tax_item
     */
    public function add_sovos_tax_rate_to_new_order_item($item, $tax_rate_id, $order)
    {
        $order_items = $order->get_items();

        foreach ($order_items as $order_item) {
            $sovos_tax = $order_item->get_meta('_sovos_tax', true);

            if ('line_item' !== $order_item->get_type() || !$sovos_tax || !isset($sovos_tax['tax_rate'])) {
                continue;
            }

            $real_rate_id = $this->insert_tax_rate($sovos_tax['tax_rate']); // ← get Woo’s int id

            $tax_rate_name = 'sovos-' . sanitize_title($sovos_tax['tax_rate']['tax_rate_name']);
            $item->set_name($tax_rate_name);
            $item->set_rate_id($real_rate_id); // ← use it here
            $item->set_tax_total($order_item->get_total_tax());
            $item->set_label($sovos_tax['tax_rate']['tax_rate_name']);
            $item->set_shipping_tax_total($sovos_tax['tax_rate']['tax_rate_shipping']);
            $item->set_compound($sovos_tax['tax_rate']['tax_rate_compound']);
            $item->set_rate_percent($sovos_tax['tax_rate']['tax_rate']);

            $tax_rate_code = $tax_rate_name . '-' . $sovos_tax['tax_rate']['tax_rate'];
            $item->set_rate_code($tax_rate_code);
        }
    }


    /**
     * Get Sovos Tax Data from Line Item
     * 
     * @param array|\WC_Order_Item - $line_item The line item.
     * 
     * @return array|bool - The Sovos tax data if it exists, false otherwise.
     * 
     * @since 1.2.0
     */
    public function get_sovos_tax_data_from_line_item( $line_item ) {
        // Check if line item is WC Order Item or Cart Item
        if ( is_array( $line_item ) ) :
            $sovos_tax = isset( $line_item['_sovos_tax'] ) ?
                $line_item['_sovos_tax'] :
                false;
        else :
            $sovos_tax = $line_item->get_meta( '_sovos_tax' ) ? : false;
        endif;

        return $sovos_tax;
    }

    /**
     * Replace the matched tax rates with a custom rate.
     *
     * @param array  $matched_tax_rates The matched tax rates.
     * @param string $country The country code.
     * @param string $state The state code.
     * @param string $postcode The postcode.
     * @param string $city The city.
     *
     * @return array The replaced tax rates.
     * 
     * @since 1.2.0
     * 
     * @hooked woocommerce_matched_tax_rates
     * 
     * TODO: check woocommerce_checkout_create_order_tax_item and WC_Order_Item_Tax functionality. This might be a better way to add the tax to the items.
     */
    public function replace_matched_tax_rates($matched_tax_rates, $country, $state, $postcode, $city)
    {
        $original_tax_rates = $matched_tax_rates;

        // ── logger helper ───────────────────────────────────────────────
        $log = function ($msg) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info($msg, ['source' => 'sovos']);
            }
        };

        $log(sprintf(
            'HIT replace_matched_tax_rates | ctx: country=%s state=%s postcode=%s city=%s',
            (string) $country,
            (string) $state,
            (string) $postcode,
            (string) $city
        ));

        // Only take over during checkout/order-review ajax/admin edits
        $is_checkout = is_checkout();
        $is_ajax = defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'woocommerce_update_order_review';
        $is_admin = is_admin();
        $log(sprintf('FLAGS: checkout=%d ajax=%d admin=%d', $is_checkout ? 1 : 0, $is_ajax ? 1 : 0, $is_admin ? 1 : 0));

        if (!$is_checkout && !$is_ajax && !$is_admin) {
            $log('EARLY RETURN: not checkout/ajax/admin');
            return $matched_tax_rates; // leave cart page alone
        }

        // Context: order in admin, otherwise cart
        global $post;
        $order_id = $post ? $post->ID : (isset($_GET['post']) ? (int) $_GET['post'] : 0);
        if (!$order_id && isset($_POST['order_id'])) {
            $order_id = (int) $_POST['order_id'];
        }
        $order = $order_id ? wc_get_order($order_id) : false;

        if ($order) {
            $log("CTX: editing order_id={$order_id}");
            if ($this->is_order_created_via_rest($order)) {
                $log('EARLY RETURN: order created via REST');
                return $matched_tax_rates;
            }
            $line_items = $order->get_items();
            $type_of_items = 'order_items';
        } else {
            $cart = $this->get_cart();
            if (!$cart) {
                $log('EARLY RETURN: no cart');
                return $matched_tax_rates;
            }
            $line_items = $cart->get_cart();
            $type_of_items = 'cart_items';
            $log('CTX: cart mode');
        }

        $li_count = is_array($line_items) ? count($line_items) : 0;
        $log("LINE ITEMS: count={$li_count}");
        if (empty($line_items)) {
            $log('EARLY RETURN: empty line items');
            return $matched_tax_rates;
        }

        $exemption_context = $this->detect_known_exemption( $line_items, $order );
        $this->sync_exemption_markers( $exemption_context, $order );

        if ( ! empty( $exemption_context['is_exempt'] ) ) {
            $log( sprintf( 'EARLY RETURN: exemption detected (%s)', $exemption_context['reason'] ?: 'unspecified' ) );
            return $original_tax_rates;
        }

        // Sovos is source of truth → start fresh (remove any WC table matches)
        $matched_tax_rates = [];

        // Quote Sovos using shared cache (session/order)
        $response = $this->get_or_create_shared_quote($line_items, $order);

        if ( ! $response ) {
            $log('EARLY RETURN: missing sovos response');
            return $original_tax_rates;
        }

        // Persist the quote for order contexts so later hooks can reuse it.
        if ($order && $response) {
            $this->persist_quote_on_order($order, $response);
        }

        $success = !empty($response['success']);
        $ln_count = isset($response['data']['lnRslts']) && is_array($response['data']['lnRslts']) ? count($response['data']['lnRslts']) : 0;
        $txAmt = isset($response['data']['txAmt']) ? $response['data']['txAmt'] : 'n/a';
        $log(sprintf('SOVOS: success=%d lnRslts=%d txAmt=%s', $success ? 1 : 0, $ln_count, (string) $txAmt));

        if (!$success) {
            $log('EARLY RETURN: sovos response success=false');
            return $original_tax_rates;
        }
        if ($ln_count === 0) {
            $log('EARLY RETURN: sovos lnRslts empty');
            return $original_tax_rates;
        }

        $total_tax_amount = is_numeric( $txAmt ) ? (float) $txAmt : null;
        if ($this->are_any_line_results_exempt($response) && ( $total_tax_amount === null || $total_tax_amount <= 0 )) {
            $log('EARLY RETURN: exempt lines (zero tax)');
            return $original_tax_rates;
        }

        // Store per-line Sovos meta and build rates
        $sovos_tax_items = $this->construct_sovos_tax_items_data($response, $line_items, $type_of_items);
        $sti_count = is_array($sovos_tax_items) ? count($sovos_tax_items) : 0;
        $log("SOVOS TAX ITEMS: count={$sti_count}");

        // Dedupe across items that share the same jurisdiction + percent
        $seen = [];

        foreach ($sovos_tax_items as $line_item) {
            $tax_rate = $this->get_sovos_tax_rate($line_item);
            if (!$tax_rate || !is_array($tax_rate)) {
                $log('SKIP: missing/invalid tax_rate on item');
                continue;
            }

            $sig = implode('|', [
                $tax_rate['tax_rate_country'] ?? '',
                $tax_rate['tax_rate_state'] ?? '',
                number_format((float) ($tax_rate['tax_rate'] ?? 0), 4),
                $tax_rate['tax_rate_class'] ?? '',
            ]);

            if (isset($seen[$sig])) {
                $log("DEDUPE: signature already added ({$sig})");
                continue;
            }
            $seen[$sig] = true;

            // Ensure a real Woo rate id (integer) and use it as the key
            $rate_id = $this->insert_tax_rate($tax_rate);
            if (isset($matched_tax_rates[$rate_id])) {
                $log("DEDUPE: rate_id already present ({$rate_id})");
                continue;
            }

            $matched_tax_rates[$rate_id] = [
                'rate' => $tax_rate['tax_rate'],      // percent
                'label' => $tax_rate['tax_rate_name'],
                'shipping' => 'no',
                'compound' => 'no',
            ];

            $log(sprintf(
                'ADD RATE: id=%s label="%s" rate=%s%%',
                (string) $rate_id,
                isset($tax_rate['tax_rate_name']) ? (string) $tax_rate['tax_rate_name'] : '',
                isset($tax_rate['tax_rate']) ? (string) $tax_rate['tax_rate'] : ''
            ));
        }

        $log('RETURNING rates: ' . print_r($matched_tax_rates, true));

        return $matched_tax_rates;
    }




    /**
     * Transfer Cart Item Meta to Order Item AND set per-line tax arrays
     *
     * Ensures taxes persist on the saved order by mapping the Sovos rate to a real
     * Woo tax rate id and attaching the per-line tax amounts to the order item.
     *
     * @hooked woocommerce_checkout_create_order_line_item
     */
    public function transfer_cart_item_meta_to_order_item($item, $cart_item_key, $values, $order)
    {
        // Skip REST-created orders per your existing rule
        if ($this->is_order_created_via_rest($order)) {
            return;
        }

        // Safety: must have product line items only
        if (!$item || 'line_item' !== $item->get_type()) {
            return;
        }

        // 1) Pull the Sovos meta that was attached to the cart line
        $sovos = isset($values['_sovos_tax']) ? $values['_sovos_tax'] : null;
        if (!$sovos || empty($sovos['tax_rate']) || !is_array($sovos['tax_rate'])) {
            return; // nothing to apply
        }

        // 2) Make sure we have a REAL Woo tax rate id (int) to reference
        $rate_id = $this->insert_tax_rate($sovos['tax_rate']); // uses get_existing_tax_rate() internally
        if (!$rate_id) {
            return; // cannot set per-line tax arrays without a valid rate id
        }

        // 3) Determine the line's tax amount
        // Prefer Woo's computed cart line tax if present (after matched_tax_rates patch, Woo will fill this)
        $line_tax = 0.0;
        if (isset($values['line_tax'])) {
            $line_tax = (float) $values['line_tax'];
        } elseif (isset($values['line_subtotal_tax'])) {
            $line_tax = (float) $values['line_subtotal_tax'];
        } elseif (isset($sovos['tax_amount'])) {
            // Optional fallback if you later store txAmt into _sovos_tax['tax_amount']
            $line_tax = (float) $sovos['tax_amount'];
        }

        // Normalize
        $line_tax = wc_format_decimal($line_tax);

        // 4) Tell Woo “this item has tax X at rate ID Y”
        // Both subtotal and total arrays keyed by the integer rate id.
        $item->set_taxes(array(
            'subtotal' => array($rate_id => $line_tax),
            'total' => array($rate_id => $line_tax),
        ));

        // 5) Keep the Sovos meta for admin/tooltips
        $item->add_meta_data('_sovos_tax', $sovos, true);
    }


    /**
     * Get Tax Rate Meta
     * 
     * @param object - $line_item The line item.
     * 
     * @return mixed array|bool - The tax rate meta if it is set, false otherwise.
     * 
     * @since 1.2.0
     */
    public function get_sovos_tax_rate( $line_item ) {
        $tax_rate   = false;
        $sovos_data = false;
        if ( is_array( $line_item ) ) :
            $sovos_data = isset( $line_item['_sovos_tax'] ) ?
                $line_item['_sovos_tax'] :
                false;
        elseif ( method_exists( $line_item, 'get_meta' ) ) :
            $sovos_data = $line_item->get_meta( '_sovos_tax' );
        endif;

        if ( $sovos_data ) :
            $tax_rate = $sovos_data['tax_rate'];
        else: // Legacy
            if ( is_array( $line_item ) ) :
                $tax_rate = $line_item['_tax_rate'];
            elseif ( method_exists( $line_item, 'get_meta' ) ) :
                $tax_rate = $line_item->get_meta( '_tax_rate' );
            endif;
        endif;

        return $tax_rate;
    }

    /**
     * Delete Temporary Tax Rates
     * 
     * @param int - $order_id The order ID.
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_checkout_order_processed
     * 
     */
    public function delete_temporary_tax_rates( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order )
            return;

        if ( $this->is_order_created_via_rest( $order ) )
            return;

        $order_items = $order->get_items();

        foreach ($order_items as $order_item):
            $tax_rate = $this->get_sovos_tax_rate($order_item);
            if (
                !is_array($tax_rate) ||
                !isset($tax_rate['tax_rate_id'])
            )
                continue;

            $tax_rate_id = $tax_rate['tax_rate_id'];

            if (method_exists('\WC_Tax', '_delete_tax_rate')) {
                \WC_Tax::_delete_tax_rate( $tax_rate_id);
            }
        endforeach;

    }

    /**
     * Hide Tax Rate ID Meta
     * 
     * @param array - $hidden_order_item_meta The hidden order item meta.
     * 
     * @return array - The hidden order item meta.
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_hidden_order_itemmeta
     * 
     */
    public function hide_tax_rate_id_meta( $hidden_order_item_meta ) {
        $hidden_order_item_meta[] = '_sovos_tax';
        return $hidden_order_item_meta;
    }

    public function recursive_tooltip_rows( $array ) {
        foreach ( $array as $key => $value ) :
            if ( is_array( $value ) ) :
                $open_tag  = '<th colspan="2">';
                $close_tag = '</th>';
            else:
                $open_tag = '<td>';
                $close_tag = '</td>';
            endif;
            ?>
            <tr><?php echo $open_tag . $key . $close_tag; ?></td>
            <?php
            if ( is_array( $value ) ) :
                $this->recursive_tooltip_rows( $value );
            else :
                ?>
                <td><?php echo $value ?></td></tr>
                <?php
            endif;
        endforeach;
    }

    /**
     * Display Sovos Tax Info as Meta in Admin
     * 
     * @param int - $item_id The item ID.
     * @param \WC_Order_Item - $item The order item.
     * @param \WC_Product - $_product The WooCommerce product.
     * 
     * @return void
     * 
     * @since 1.2.0
     * 
     * @hooked woocommerce_after_order_itemmeta
     * 
     */
    public function display_sovos_tax_in_admin( $item_id, $item, $_product ) {
        $sovos_tax = $item->get_meta( '_sovos_tax', true );
        if ( ! $sovos_tax )
            return;

        /**
         * Allow modification of the $sovos_tax array before output
         * 
         * @param array - $sovos_tax array element
         * 
         * @return array - $sovos_tax array element
         * 
         * @since   1.2.0
         * 
         * @hook modify_sovos_tax_tooltip
         */
        $sovos_tax = apply_filters( 'modify_sovos_tax_tooltip', $sovos_tax );
        // Remove all array elements that start with '_' as they are meant to be hidden.
        foreach ( $sovos_tax as $key => $value ) :
            if ( strpos( $key, '_' ) === 0 )
                unset( $sovos_tax[$key] );
        endforeach;

        ob_start();
        ?>
        <div class="sovos-order-notes-tooltip sovos-tax-tooltip">
            SOVOS Tax Info
            <span class="sovos-tax-tooltip-text">
                <table>
                    <?php $this->recursive_tooltip_rows($sovos_tax); ?>
                </table>
            </span>
        </div>
        <?php

        $output = ob_get_clean();
        echo $output;
    }

    /**
     * Temporarily Add Tax Rate
     * 
     * @param \WC_Order - $order The WooCommerce order object.
     * @param \WC_Data_Store - $data_store The WooCommerce data store.
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_before_order_object_save
     * 
     */
    public function temporarily_add_tax_rate_on_recalculation( $order, $data_store = null ) {
        if (
            ! defined( 'DOING_AJAX' ) ||
            ! DOING_AJAX ||
            ! isset( $_POST['action'] ) ||
            $_POST['action'] !== 'woocommerce_calc_line_taxes'
        )
            return;

        // Get the tax rates from the order items
        $order_items = $order->get_items();

        // Start temp rate ids
        if ( ! isset( $GLOBALS['temp_tax_rate_ids'] ) )
            $GLOBALS['temp_tax_rate_ids'] = array();

        foreach ( $order_items as $order_item ) :
            $tax_rate = $this->get_sovos_tax_rate( $order_item );

            if ( empty( $tax_rate ) || ! is_array( $tax_rate ) )
                continue;

            $key = $order_item->get_id();

            // Only insert the tax rate if it hasn't been inserted yet
            if ( ! isset( $GLOBALS['temp_tax_rate_ids'][$key] ) ) :
                // Insert the tax rate
                $tax_rate_id = $this->insert_tax_rate( $tax_rate );

                // Store the tax rate ID
                $GLOBALS['temp_tax_rate_ids'][$key] = $tax_rate_id;
            endif;
        endforeach; //endforeach ( $order_items as $order_item ) :
    }

    /**
     * Set Tax Calculation Done Flag
     * 
     * @param \WC_Order - $order The WooCommerce order object.
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked woocommerce_order_after_calculate_totals
     */
    public function set_tax_calculation_done_flag( $order ) {
        $GLOBALS['tax_calculation_done'] = true;
    }

    /**
     * Delete Temporary Tax Rate After Recalculation
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked shutdown
     * 
     */
    public function delete_temporary_tax_rate_after_recalculation() {
        // Only delete the tax rates if they haven't been deleted yet
        if (
            ! isset( $GLOBALS['temp_tax_rate_ids'])||
            ! isset( $GLOBALS['tax_calculation_done'] ) ||
            ! $GLOBALS['tax_calculation_done']
        )
            return;

        // Delete the tax rate
        foreach ( $GLOBALS['temp_tax_rate_ids'] as $tax_rate_id ) :
            if (method_exists('\WC_Tax', '_delete_tax_rate')) {
                \WC_Tax::_delete_tax_rate( $tax_rate_id );
            }
        endforeach;

        // Unset the global variable to indicate that the tax rates have been deleted
        unset( $GLOBALS['temp_tax_rate_ids'] );
    }

    /**
     * Add Temporary Tax Rates Actions
     * 
     * @return void
     * 
     * @since 1.0.0
     * 
     * @hooked init
     * 
     */
    public function add_temp_tax_rates_actions() {

        // Set tax rates before calculating totals
        add_action( 'woocommerce_before_order_object_save', [$this, 'temporarily_add_tax_rate_on_recalculation'], 10, 2 );

        // Set tax calculation done flag after recalculation
        add_action( 'woocommerce_order_after_calculate_totals', [$this, 'set_tax_calculation_done_flag'], 10, 1 );

        // Use the shutdown action to delete the tax rate
        add_action( 'shutdown', [$this, 'delete_temporary_tax_rate_after_recalculation'] );
    }

    /**
     * Add Sovos Transaction ID to Refund
     * 
     * @param int - $order_id The WooCommerce Order ID for the Refund.
     * @param int - $refund_id The WooCommerce Refund ID
     * 
     * @return void
     * 
     * @since 1.1.0
     * 
     * @hooked woocommerce_order_refunded
     * 
     */
    public function order_status_refunded( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) 
            return;

        $this->temporarily_add_tax_rate_on_recalculation( $order );
    }

    /**
     * Refund Tax
     * 
     * @param int - $refund_id The refund ID.
     * @param array - $refund_items The refund items.
     * 
     * @return mixed array|bool - The response from the tax service if it is valid, false otherwise.
     * 
     * @since 1.1.0
     */
    public function refund_tax( $refund_id, $refund_items ) {
        $tax_service = $this->get_tax_service();
        $tax_service = $this->prepare_tax_service( $refund_items );
        $result      = $tax_service->refund( $refund_id );

        // Clear the TaxService to Prevent Duplicates
        $tax_service->clearTaxService();

        return $result;
    }

    /**
     * Add Sovos Transaction ID to Refund
     * 
     * @param \WC_Order_Refund - $refund The WooCommerce refund object.
     * @param string - $transaction_id The Sovos transaction ID.
     * 
     * @return void
     * 
     * @since 1.1.0
     * 
     * @hooked woocommerce_order_refunded
     * 
     */
    public function add_sovos_transaction_id_to_refund( $refund, $transaction_id ) {
        $order_id = $refund->get_parent_id();
        $order    = wc_get_order( $order_id );

        if ( $this->is_order_created_via_rest( $order ) )
            return;

        // Store the txwTrnDocId as order meta data.
        // TODO: Store the txwTrnDocId as element of '_sovos_tax' array meta data.
        $refund->update_meta_data( 'txwTrnDocId', $transaction_id );
        $refund->save();

    }

    /**
     * Display Sovos Transaction ID on Refund
     * 
     * @param \WC_Order_Refund - $refund The WooCommerce refund object.
     * 
     * @return void
     * 
     * @since 1.1.0
     * 
     * @hooked woocommerce_order_details_after_order_table
     * 
     */
    public function display_sovos_transaction_id_on_refund( $refund ) {
        $transaction_id = $refund->get_meta( 'txwTrnDocId', true );
        if ( $transaction_id )
            echo '<p><strong>SOVOS Transaction ID:</strong> ' . esc_html( $transaction_id ) . '</p>';
    }

    /**
     * Send Refund Request
     * 
     * @param int - $refund_id The refund ID.
     * @param array - $args The refund arguments.
     * 
     * @return void
     * 
     * @since 1.1.0
     * 
     * @hooked woocommerce_order_refunded
     * 
     */
    public function send_refund_request( $refund_id, $args ) {
        $refund = wc_get_order( $refund_id );

        if ( ! $refund )
            return;

        $refund_items = $refund->get_items();

        if ( ! $refund_items )
            return;

        $response       = $this->refund_tax( $refund_id, $refund_items );
        $transaction_id = isset( $response['data'] ) && isset( $response['data']['txwTrnDocId'] ) ?
            $response['data']['txwTrnDocId'] :
            null;

        $order_note = '';
        // Get the order associated with the refund
        $refund     = wc_get_order( $refund_id );
        $order_id   = $refund->get_parent_id();
        $order      = wc_get_order( $order_id );

        // Check if the response is valid.
        if ( $response['success'] ) :
            // Add a note to the order
            if ( isset( $response['data']['txAmt'] ) )
                $order_note .= "SOVOS Tax refunded: {$response['data']['txAmt']}";

            if ( $transaction_id ) :
                $this->add_sovos_transaction_id_to_refund( $refund, $transaction_id );
                $order_note .= "\n SOVOS Refund Transaction ID: $transaction_id";
            endif;
        elseif( isset(
            $response['message']['errorCode'],
            $response['message']['errorMessage']
        ) ):
            $order_note .= 'SOVOS Tax Failed to Refund:';
            $error_code    = $response['message']['errorCode'];
            $error_message = $response['message']['errorMessage'];
            $order_note .= "Error Code: $error_code\n Error Message: $error_message";
        endif;

        if ( ! empty( $order_note ) )
            $order->add_order_note( $order_note );
    }

    /** -----------------------------------------------------------------
     *  🔐 NEW: Helpers for request‑scoped caching
     *  -----------------------------------------------------------------*/

    /**
     * Build a deterministi csignature of “what the quote depends on”.
     * – Cart contents (product‑id, qty, line total)  
     * – Destination address (the parts Sovos needs)
     */
    protected function generate_cache_key( array $line_items ): string {
        $address = $this->set_to_address();           // already returns a trimmed array
        $cart    = $this->get_cart();
        $session = $this->get_wc_session();

        $shipping_methods = [];
        if ( $session ) {
            $chosen_methods = $session->get( 'chosen_shipping_methods' );
            if ( is_array( $chosen_methods ) ) {
                $shipping_methods = array_values( array_filter( $chosen_methods ) );
            }
        }

        $coupons          = [];
        $fees             = [];
        $line_tax_classes = [];

        if ( $cart ) {
            $coupons = array_values( $cart->get_applied_coupons() );

            foreach ( $cart->get_fees() as $fee ) {
                $fees[] = [
                    'id'        => isset( $fee->id ) ? $fee->id : $fee->name,
                    'name'      => $fee->name,
                    'amount'    => $fee->amount,
                    'taxable'   => $fee->taxable,
                    'tax_class' => $fee->tax_class,
                ];
            }
        }

        $customer_roles = [];
        $customer       = function_exists( 'WC' ) && WC()->customer ? WC()->customer : null;

        if ( is_user_logged_in() ) {
            $user           = wp_get_current_user();
            $customer_roles = (array) $user->roles;
        }

        $tax_class_markers = [
            'session_original_tax_class' => $session ? $session->get( 'original_tax_class' ) : null,
            'using_original_tax_class'   => $session ? $session->get( 'use_original_tax_class' ) : null,
            'customer_tax_class'         => ( $customer && method_exists( $customer, 'get_tax_class' ) ) ? $customer->get_tax_class() : null,
            'customer_vat_exempt'        => ( $customer && method_exists( $customer, 'get_is_vat_exempt' ) ) ? $customer->get_is_vat_exempt() : null,
        ];

        $line_items_payload = array_map(
            static function ( $item ) use ( &$line_tax_classes ) {
                $product_id = is_array( $item ) ? $item['data']->get_id() : $item->get_product_id();
                $quantity   = is_array( $item ) ? $item['quantity']       : $item->get_quantity();
                $tax_class  = is_array( $item ) ? $item['data']->get_tax_class() : $item->get_tax_class();

                $line_tax_classes[] = $tax_class;

                return [
                    'id'        => $product_id,
                    'qty'       => $quantity,
                    'tax_class' => $tax_class,
                    // remove 'tot' — line_total mutates after taxes, causing hash churn
                ];
            },
            $line_items
        );

        $exemption_context = $this->collect_exemption_context( $line_items );

        $payload = [
            'addr'          => $address,
            'cart'          => $line_items_payload,
            'shipping'      => $shipping_methods,
            'coupons'       => $coupons,
            'fees'          => $fees,
            'customer'      => [
                'roles'       => $customer_roles,
                'tax_markers' => $tax_class_markers,
            ],
            'exemption'     => [
                'product_flags'       => $exemption_context['product_flags'],
                'all_products_exempt' => $exemption_context['all_products_exempt'],
                'vat_exempt'          => $exemption_context['vat_exempt'],
                'email_exempt'        => $exemption_context['email_exempt'],
                'email'               => $exemption_context['email'],
                'allowlist_hash'      => $exemption_context['allowlist_hash'],
                'session_exempt'      => $session ? $session->get( 'sovos_is_exempt' ) : null,
            ],
            'line_classes'  => $line_tax_classes,
        ];

        return md5( wp_json_encode( $payload ) );     // 32‑char cache key
    }

    /** Read / write helpers around WC()->session */
    protected function get_cached_quote( string $key ) {
        if ( isset( $this->runtime_quote_cache[ $key ] ) && $this->is_valid_quote_response( $this->runtime_quote_cache[ $key ] ) ) {
            return $this->runtime_quote_cache[ $key ];
        }

        $session = $this->get_wc_session();
        $response = $session ? $session->get( "sovos_quote_$key" ) : false;

        if ( ! $this->is_valid_quote_response( $response ) ) {
            // Fallback to transient in case WC session is not yet populated.
            $response = get_transient( $this->get_quote_response_transient_key( $key ) );
            if ( $this->is_valid_quote_response( $response ) && $session ) {
                // Rehydrate session cache for subsequent requests.
                $session->set( "sovos_quote_$key", $response );
            }
        }

        return $this->is_valid_quote_response( $response ) ? $response : false;
    }

    protected function set_cached_quote( string $key, array $response ): void {
        if ( ! $this->is_valid_quote_response( $response ) ) {
            return;
        }

        // Always memoize for the current request.
        $this->runtime_quote_cache[ $key ] = $response;

        $session = $this->get_wc_session();
        if ( $session ) {
            $session->set( "sovos_quote_$key", $response );
            // Track keys so we can clear transients later.
            $keys = $session->get( 'sovos_quote_keys' );
            if ( ! is_array( $keys ) ) {
                $keys = [];
            }
            if ( ! in_array( $key, $keys, true ) ) {
                $keys[] = $key;
                $session->set( 'sovos_quote_keys', $keys );
            }
        }

        // Always write a transient so concurrent PHP requests see the cached response even before WC session hydration.
        set_transient( $this->get_quote_response_transient_key( $key ), $response, 5 * MINUTE_IN_SECONDS );
    }

    /**
     * 🔐 Quote locks to avoid duplicate outbound calls for the same cart/address.
     * Uses a transient so concurrent PHP requests see the lock immediately.
     */
    protected function get_quote_lock_key( string $cache_key ): string {
        return "sovos_quote_lock_$cache_key";
    }

    protected function get_quote_response_transient_key( string $cache_key ): string {
        return "sovos_quote_resp_$cache_key";
    }

    protected function has_active_quote_lock( string $lock_key ): bool {
        $lock = get_transient( $lock_key );
        if ( ! is_array( $lock ) || empty( $lock['ts'] ) ) {
            return false;
        }

        // Expire stale locks (default 5s)
        if ( time() - (int) $lock['ts'] > 5 ) {
            $this->clear_quote_lock( $lock_key );
            return false;
        }

        return true;
    }

    protected function acquire_quote_lock( string $lock_key ): bool {
        if ( $this->has_active_quote_lock( $lock_key ) ) {
            return false;
        }

        // set_transient is shared across concurrent requests.
        return (bool) set_transient( $lock_key, [ 'ts' => time() ], 10 );
    }

    protected function clear_quote_lock( string $lock_key ): void {
        delete_transient( $lock_key );
    }

    /**
     * When a lock exists, wait briefly for the first request to populate the cache.
     */
    protected function wait_for_cached_quote( string $cache_key ) {
        $attempts = 30; // ~3s total
        while ( $attempts-- > 0 ) {
            $cached = $this->get_cached_quote( $cache_key );
            if ( $cached ) {
                return $cached;
            }
            usleep( 100000 ); // 100ms
        }

        return false;
    }

    public function clear_tax_quote_cache( $order_id ): void {    
        $session = WC()->session;
    
        if ( ! $session || ! method_exists( $session, 'get_session_data' ) ) {
            return;
        }
    
        $data = $session->get_session_data();
        if ( ! is_array( $data ) ) {
            return;
        }
    
        try {
            // Clear session-scoped caches.
            foreach ( $data as $key => $value ) {
                if ( strpos( $key, 'sovos_quote_' ) === 0 ) {
                    $session->__unset( $key );
                }
            }

            // Clear transient response caches using tracked keys.
            $tracked_keys = $session->get( 'sovos_quote_keys' );
            if ( is_array( $tracked_keys ) ) {
                foreach ( $tracked_keys as $tracked_key ) {
                    delete_transient( $this->get_quote_response_transient_key( $tracked_key ) );
                }
            }
            $session->__unset( 'sovos_quote_keys' );
        } catch ( Exception $e ) {
            error_log("Exception while clearing tax quote cache: " . $e->getMessage());
        }
    }
    /**
     * Finalize order taxes after all items are created.
     *
     * @hooked woocommerce_checkout_create_order  (priority 100)
     */
    public function finalize_order_taxes_on_create($order, $data)
    {
        if ( $this->is_exempt_via_session_or_order( $order ) ) {
            $session = $this->get_wc_session();

            $order->update_meta_data( '_sovos_is_exempt', true );

            if ( $session ) {
                $reason = $session->get( 'sovos_exempt_reason' );
                $email  = $session->get( 'sovos_exempt_email' );

                if ( $reason )
                    $order->update_meta_data( '_sovos_exempt_reason', $reason );

                if ( $email )
                    $order->update_meta_data( '_sovos_exempt_email', $email );
            }

            $order->save_meta_data();

            return;
        }

        // Skip REST-created orders as in your other logic
        if ($this->is_order_created_via_rest($order)) {
            return;
        }

        // Persist any cached quote from the checkout session onto the order.
        $session_quote = $this->get_cached_quote_from_session();
        if ($session_quote) {
            $this->persist_quote_on_order($order, $session_quote);
        }
        // Let Woo build order-level tax items from the per-line arrays we just set.
        $order->calculate_taxes();
        $order->save();
    }
}

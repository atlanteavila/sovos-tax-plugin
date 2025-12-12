<?php
/**
 * Private.
 * 
 * @since   1.0.0
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS;

class Woo_Sovos_Private {

    /**
     * Plugin name.
     */
    private $plugin_name;

    /**
     * Plugin version.
     */
    private $plugin_version;

    /**
     * Public
     */
    private $public;

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

        $this->public = Woo_Sovos_Public::get_instance( $plugin_name, $plugin_version );

    }

    /**
     * Enqueue styles.
     * 
     * @since   1.0.0
     */
    public function enqueue_styles() {

        // Styles.
        wp_enqueue_style( $this->plugin_name, WOO_SOVOS_URI . 'private/assets/css/styles.css', [], $this->plugin_version, 'all' );

        // Check if is wp-admin edit order page.
        if( is_admin() && get_current_screen()->id === 'shop_order' ) :
            // Edit Order Styles.
            $file = 'private/assets/css/edit-order-styles.css';
            wp_enqueue_style( $this->plugin_name . '-edit-order-styles', WOO_SOVOS_URI . $file, [], $this->public->cache_bust_version( WOO_SOVOS_PATH . $file ), 'all' );
        endif;

    }

    /**
     * Enqueue scripts.
     * 
     * @since   1.0.0
     */
    public function enqueue_scripts() {

        // Scripts.
        wp_enqueue_script( $this->plugin_name, WOO_SOVOS_URI . 'private/assets/js/scripts.js', [], $this->plugin_version, false );

        // Scripts.
        // $file = 'public/assets/js/scripts.js';
        // wp_enqueue_script( $this->plugin_name, WOO_SOVOS_URI . $file, ['jquery'], $this->public->cache_bust_version( WOO_SOVOS_PATH . $file ), false );

    }

}

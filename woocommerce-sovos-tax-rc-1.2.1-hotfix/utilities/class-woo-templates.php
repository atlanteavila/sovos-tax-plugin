<?php
/**
 * WooCommerce Templates.
 * 
 * Override WooCommerce templates from the plugin and then the theme. Changes priority to plugin > theme > WooCommerce. Install templates into /public/views.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\WooTemplates;

class Woo_Sovos_WooTemplates {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if WooCommerce is active.
        if( ! class_exists( 'WooCommerce' ) ) return;

        // Filters.
        add_filter( 'woocommerce_locate_template', [ $this, 'template' ], 10, 3 );

    }

    /**
     * Locate template.
     * 
     * @since   1.0.0
     */
    public function template( $template, $name, $path ) {

        // Global.
        global $woocommerce;

        // Set template.
        $_template = $template;

        // Check path.
        if( ! $path ) $path = $woocommerce->template_url;

        // Set plugin path.
        $plugin_path  = WOO_SOVOS_PATH  . 'public/views/';

        // Set plugin template.
        $template = locate_template( [
            $path . $name,
            $name
        ] );

        // Check for theme template.
        if( ! $template && file_exists( $plugin_path . $name ) )
            $template = $plugin_path . $name;

        // Check template.
        if( ! $template )
            $template = $_template;

        // Return.
        return $template;

    }

}
new Woo_Sovos_WooTemplates();





$this->loader->add_filter( 'woocommerce_locate_template', $private, 'cc_woocommerce_locate_template', 10, 3 );

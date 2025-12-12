<?php
/**
 * WooCommerce Log.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\WooLog;

class Woo_Sovos_WooLog {

    /**
     * Debug.
     * 
     * @since   1.0.0
     */
    public function debug( $message ) {

        // Check if logger exists.
        if( function_exists( 'wc_get_logger' ) ) {

            // Get logged.
            $log = wc_get_logger();

            // Set context.
            $context = [ 'source' => 'woo_sovos' ];

            // Add timestamp.
            $message = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;

            // Debug.
            $log->debug( print_r( $message, true ), $context );

        }

    }

    /**
     * Error.
     * 
     * @since   1.0.0
     */
    public function error( $message ) {

        // Check if logger exists.
        if( function_exists( 'wc_get_logger' ) ) {

            // Get logged.
            $log = wc_get_logger();

            // Set context.
            $context = [ 'source' => 'woo_sovos' ];

            // Add timestamp.
            $message = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;

            // Error.
            $log->error( print_r( $message, true ), $context );

        }

    }

}
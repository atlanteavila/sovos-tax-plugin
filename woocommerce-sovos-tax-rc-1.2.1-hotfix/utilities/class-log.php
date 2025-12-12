<?php
/**
 * Log.
 * 
 * Works to log using MonoLog.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\Log;

class Woo_Sovos_Log {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    private $log;

    /**
     * Construct.
     * 
     * @param   string  $type   The type of log.
     * 
     * @since   1.0.0
     */
    public function __construct( $type = 'info' ) {

        // Set logger.
        $this->log = new \Monolog\Logger( 'woo_sovos' );

        // Set handler.
        $this->log->pushHandler( new \Monolog\Handler\StreamHandler( WOO_SOVOS_PATH . '/logs/woo_sovos_' . $this->set_type( $type ) . '.log', \Monolog\Logger::DEBUG ) );

    }

    /**
     * Debug.
     * 
     * @since   1.0.0
     */
    public function debug( $message ) {

        // Add timestamp.
        $message = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;

        // Debug.
        $this->log->debug( print_r( $message, true ) );

    }

    /**
     * Error.
     * 
     * @since   1.0.0
     */
    public function error( $message ) {

        // Add timestamp.
        $message = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;

        // Error.
        $this->log->error( print_r( $message, true ) );

    }

    /**
     * Info.
     * 
     * @since   1.0.0
     */
    public function info( $message ) {

        // Add timestamp.
        $message = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;

        // Info.
        $this->log->info( print_r( $message, true ) );

    }

    /**
     * Clean and set type.
     * 
     * @since   1.0.0
     */
    public function set_type( $type ) {

        // Convert to lowercase.
        $type = strtolower( $type );

        // Replace spaces with dashes.
        $type = str_replace( ' ', '-', $type );

        // Remove characters that are not alphanumeric or dashes.
        $type = preg_replace( '/[^a-z0-9-]/', '', $type );

        // Remove consecutive dashes.
        $type = preg_replace( '/-+/', '-', $type );

        // Return.
        return $type;

    }

}
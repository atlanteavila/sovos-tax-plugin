<?php
/**
 * API.
 * 
 * Can be used raw, for making API requests, or can be copied and used as a template for specific API interactions.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\API;

// Uses.
use BuiltMighty\WOO_SOVOS\Utility\Log\Woo_Sovos_Log;
 
class Woo_Sovos_API {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    protected   $log;
    private     $api_url;
    private     $auth;

    /**
     * Construct.
     * 
     * @param   string  $api_url    The URL of the API.
     * @param   string  $type       Authentication type.
     * 
     * @since   1.0.0
     */
    public function __construct( $api_url, $type ) {

        // Set logger.
        $this->log = new Woo_Sovos_Log( 'api' );

        // Set API URL.
        $this->api_url = $api_url;

        // Set authentication.
        $this->auth['type'] = $type;

    }

    /**
     * Set args.
     * 
     * @param   array   $body   The body of the request.
     * @param   string  $method The method of the request.
     * @return  array           The args for the request.
     * 
     * @since   1.0.0
     */
    public function get_args( $body, $method = 'POST' ) {

        // Set body.
        $body = json_encode( $body );

        // Set args.
        $args = [
            'method'    => $method,
            'headers'   => [
                'Content-Type'      => 'application/json',
                'Authorization'     => $this->get_auth(),
                'Content-Length'    => strlen( $body )
            ],
            'body'      => $body,
        ];

        // Return.
        return $args;

    }

    /**
     * Get auth.
     * 
     * @return  string  The authentication.
     * 
     * @since   1.0.0
     */
    public function get_auth() {

        // Check if auth is set.
        if( isset( $this->auth['type'] ) ) {

            // Check auth type.
            switch( $this->auth['type'] ) {

                // Basic.
                case 'basic':
                    $auth = 'Basic ' . base64_encode( $this->auth['username'] . ':' . $this->auth['password'] );
                    break;

                // Bearer.
                case 'bearer':
                    $auth = 'Bearer ' . $this->auth['token'];
                    break;

                // Default.
                default:
                    $auth = '';
                    break;

            }

        }

        // Return.
        return $auth;

    }

    /**
     * Request.
     * 
     * @param   string  $endpoint   The endpoint of the request.
     * @param   array   $args       The args for the request.
     * 
     * @since   1.0.0
     */
    public function request( $endpoint, $args ) {

        // Request.
        $response = json_decode( wp_remote_retrieve_body( wp_remote_request( $this->api_url . $endpoint, $args ) ), true );

        // Log.
        $this->log->debug( 'API Request: ' . $this->api_url . $endpoint . ' - ' . print_r( $response, true ) );

        // Return.
        return $response;

    }

}
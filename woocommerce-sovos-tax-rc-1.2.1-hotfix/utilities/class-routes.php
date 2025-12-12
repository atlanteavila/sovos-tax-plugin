<?php
/**
 * Routes.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\Routes;

// Uses.
use BuiltMighty\WOO_SOVOS\Utility\Log\Woo_Sovos_Log;

class Woo_Sovos_Routes {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    protected   $log;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Set logger.
        $this->log = new Woo_Sovos_Log( 'routes' );

        // Actions.
        add_action( 'rest_api_init', [ $this, 'endpoint' ] );

    }

    /**
     * Add endpoint.
     * 
     * @since   1.0.0
     */
    public function endpoint() {

        // Register.
        register_rest_route( 'woo-sovos/v1', 'endpoint', [
            'methods'               => 'GET',
            'callback'              => [ $this, 'endpoint_return' ],
            'permission_callback'   => [ $this, 'permissions' ],
        ] );

    }

    /**
     * Respond to API endpoint request.
     * 
     * @since   1.0.0
     */
    public function endpoint_return( $request ) {

        // Parse request.
        $request = $request->get_params();

        // Check for request.
        if( empty( $request ) ) {
            
            // Return error.
            return new WP_Error(
                'error',
                'There was an error',
                [ 'status' => 404 ],
            );

        }

        // Log request.
        $this->log->debug( 'Request: ' . print_r( $request, true ) );

        // Multi-dimensional array of data to be returned.
        $data_response = [ 'status' => 'success' ];

        // Set response.
        $response = new WP_REST_Response( $data_response );
        $response->set_status( 200 );

        // Return.
        return $response;

    }

    /**
     * Permissions.
     * 
     * @since   1.0.0
     */
    public function permissions() {

        // To allow all access (not recommended).
        return true;

        // If a WooCommerce API key is passed with the request, you can check it in this way.
        /*if( !current_user_can( 'manage_options' ) ) {

            // Error.
            return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed access.' ), [ 'status' => '401' ] );

        }*/

        // You can also do other custom authorization checks.

    }

}
new Woo_Sovos_Routes();
<?php
/**
 * Database.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\DB;

class Woo_Sovos_DB {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    public $db;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Get database.
        global $wpdb;
        $this->db = $wpdb;

    }

    /**
     * Request.
     * 
     * @param   string  $query  Query to be executed. When referencing the table, you can freely use wp_ as the prefix and if it's a different prefix, the code with compensate.
     * @param   string  $type   Type of request. Can be results, row, or var.
     * 
     * @since   1.0.0
     */
    public function request( $query, $type ) {

        // Replace prefix in query.
        $query = str_replace( 'wp_', $this->db->prefix, $query );

        // Switch between types.
        switch( $type ) {

            // Results.
            case 'results':
                return $this->db->get_results( $query, ARRAY_A );
                break;

            // Row.
            case 'row':
                return $this->db->get_row( $query, ARRAY_A );
                break;

            // Var.
            case 'var':
                return $this->db->get_var( $query, ARRAY_A );
                break;

            // Default.
            default:
                return $this->db->get_results( $query, ARRAY_A );
                break;

        }

    }

    /**
     * Insert.
     * 
     * @param   string  $table  Table to insert into.
     * @param   array   $data   Data to insert.
     * 
     * @since   1.0.0
     */
    public function insert( $table, $data ) {

        // Replace prefix in table.
        $table = str_replace( 'wp_', $this->db->prefix, $table );

        // Insert.
        $this->db->insert( $table, $data );

    }

    /**
     * Update.
     * 
     * @param   string  $table  Table to update.
     * @param   array   $data   Data to update.
     * @param   array   $where  Where to update.
     * 
     * @since   1.0.0
     */
    public function update( $table, $data, $where ) {

        // Replace prefix in table.
        $table = str_replace( 'wp_', $this->db->prefix, $table );

        // Update.
        $this->db->update( $table, $data, $where );

    }

}
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

    /**
     * Render a per-product Sovos exemption flag in the General tab.
     */
    public function render_product_exemption_field() {
        if ( ! function_exists( 'woocommerce_wp_checkbox' ) )
            return;

        echo '<div class="options_group">';
        woocommerce_wp_checkbox([
            'id'            => '_sovos_always_exempt',
            'label'         => __( 'Always exempt from Sovos', 'woo-sovos' ),
            'description'   => __( 'Skip Sovos tax calls for this product. Stored in the _sovos_always_exempt product meta key.', 'woo-sovos' ),
            'desc_tip'      => true,
            'value'         => get_post_meta( get_the_ID(), '_sovos_always_exempt', true ),
        ]);
        echo '</div>';
    }

    /**
     * Persist the per-product exemption flag.
     */
    public function save_product_exemption_field( $product ) {
        if ( ! $product || ! $product instanceof \WC_Product )
            return;

        $is_exempt = isset( $_POST['_sovos_always_exempt'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_sovos_always_exempt', $is_exempt );
    }

    /**
     * Show user-level Sovos exemption allowlists.
     */
    public function render_user_exemption_fields( $user ) {
        if ( ! $user instanceof \WP_User )
            return;

        $primary   = implode( "\n", $this->prepare_entries_for_display( get_user_meta( $user->ID, '_sovos_exempt_emails', true ) ) );
        $alternate = implode( "\n", $this->prepare_entries_for_display( get_user_meta( $user->ID, '_sovos_exempt_alternate_emails', true ) ) );
        ?>
        <h3><?php esc_html_e( 'Sovos Tax Exemptions', 'woo-sovos' ); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="_sovos_exempt_emails"><?php esc_html_e( 'Primary emails/domains', 'woo-sovos' ); ?></label></th>
                <td>
                    <textarea name="_sovos_exempt_emails" id="_sovos_exempt_emails" class="regular-text" rows="4" placeholder="customer@example.com&#10;@example.com"><?php echo esc_textarea( $primary ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One email or domain per line. Domains can start with @ (e.g. @example.com). Saved to the _sovos_exempt_emails meta key.', 'woo-sovos' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="_sovos_exempt_alternate_emails"><?php esc_html_e( 'Alternate emails/domains', 'woo-sovos' ); ?></label></th>
                <td>
                    <textarea name="_sovos_exempt_alternate_emails" id="_sovos_exempt_alternate_emails" class="regular-text" rows="4" placeholder="alt@example.com&#10;@alt-domain.com"><?php echo esc_textarea( $alternate ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One email or domain per line for alternates. Saved to the _sovos_exempt_alternate_emails meta key.', 'woo-sovos' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user-level exemption allowlists.
     */
    public function save_user_exemption_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) )
            return;

        $primary   = isset( $_POST['_sovos_exempt_emails'] ) ? $_POST['_sovos_exempt_emails'] : '';
        $alternate = isset( $_POST['_sovos_exempt_alternate_emails'] ) ? $_POST['_sovos_exempt_alternate_emails'] : '';

        update_user_meta( $user_id, '_sovos_exempt_emails', implode( "\n", $this->sanitize_exemption_entries( $primary ) ) );
        update_user_meta( $user_id, '_sovos_exempt_alternate_emails', implode( "\n", $this->sanitize_exemption_entries( $alternate ) ) );
    }

    /**
     * Normalize posted entries into a sanitized list.
     */
    protected function sanitize_exemption_entries( $raw_entries ) {
        $entries = is_array( $raw_entries ) ? $raw_entries : preg_split( '/[\r\n,]+/', (string) $raw_entries );
        $entries = array_map( 'trim', $entries );
        $entries = array_filter( $entries );

        return array_map( 'sanitize_text_field', $entries );
    }

    /**
     * Prepare stored values for display in textareas.
     */
    protected function prepare_entries_for_display( $value ) {
        $entries = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
        $entries = array_map( 'trim', $entries );
        $entries = array_filter( $entries );

        return $entries;
    }

}

<?php
/**
 * Custom Taxonomies.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\Taxonomies;

class Woo_Sovos_Taxonomies {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Actions.
        add_action( 'init', [ $this, 'register' ], 0 );

    }

    /**
     * Define.
     * 
     * @since   1.0.0
     */
    public function define() {

        // Set taxonomies.
        $taxonomies = [
            'custom_taxonomy'   => [
                'singular'      => 'custom taxonomy',
                'plural'        => 'custom taxonomies',
                'post_types'    => [ 'post' ],
                'args'          => [
                    'label'                 => __( 'Custom Taxonomy', WOO_SOVOS_DOMAIN ),
                    'description'           => __( 'Custom Taxonomy Description', WOO_SOVOS_DOMAIN ),
                    'public'                => true,
                    'hierarchical'          => true,
                    'show_ui'               => true,
                    'show_in_menu'          => true,
                    'show_in_nav_menus'     => true,
                    'show_tagcloud'         => true,
                    'show_in_quick_edit'    => true,
                    'show_admin_column'     => true,
                    'rewrite'               => [
                        'slug'              => 'custom-taxonomy',
                        'with_front'        => true,
                        'hierarchical'      => true,
                    ],
                ],
            ],
        ];

        // Return.
        return $taxonomies;

    }

    /**
     * Register.
     * 
     * @since   1.0.0
     */
    public function register() {

        // Loop through taxonomies.
        foreach( $this->define() as $taxonomy => $tax ) {

            // Set labels.
            $labels = [
                'name'                       => _x( ucwords( $tax['singular'] ), 'Taxonomy General Name', 'text_domain' ),
                'singular_name'              => _x( ucwords( $tax['singular'] ), 'Taxonomy Singular Name', 'text_domain' ),
                'menu_name'                  => __( ucwords( $tax['singular'] ), 'text_domain' ),
                'all_items'                  => __( 'All ' . ucwords( $tax['plural'] ), 'text_domain' ),
                'parent_item'                => __( 'Parent ' . ucwords( $tax['singular'] ), 'text_domain' ),
                'parent_item_colon'          => __( 'Parent ' . ucwords( $tax['singular'] ) . ':', 'text_domain' ),
                'new_item_name'              => __( 'New ' . ucwords( $tax['singular'] ) . ' Name', 'text_domain' ),
                'add_new_item'               => __( 'Add New ' . ucwords( $tax['singular'] ), 'text_domain' ),
                'edit_item'                  => __( 'Edit ' . ucwords( $tax['singular'] ), 'text_domain' ),
                'update_item'                => __( 'Update ' . ucwords( $tax['singular'] ), 'text_domain' ),
                'view_item'                  => __( 'View ' . ucwords( $tax['singular'] ), 'text_domain' ),
                'separate_items_with_commas' => __( 'Separate ' . $tax['singular'] . ' with commas', 'text_domain' ),
                'add_or_remove_items'        => __( 'Add or remove ' . $tax['plural'], 'text_domain' ),
                'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
                'popular_items'              => __( 'Popular ' . ucwords( $tax['plural'] ), 'text_domain' ),
                'search_items'               => __( 'Search ' . ucwords( $tax['plural'] ), 'text_domain' ),
                'not_found'                  => __( 'Not Found', 'text_domain' ),
                'no_terms'                   => __( 'No ' . $tax['plural'], 'text_domain' ),
                'items_list'                 => __( ucwords( $tax['plural'] ) . ' list', 'text_domain' ),
                'items_list_navigation'      => __( ucwords( $tax['plural'] ) . ' list navigation', 'text_domain' ),
            ];

            // Add labels to args.
            $tax['args']['labels'] = $labels;
            
            // Register taxonomy.
            register_taxonomy( $taxonomy, $tax['post_types'], $tax['args'] );

        }

    }

}
new Woo_Sovos_Taxonomies();
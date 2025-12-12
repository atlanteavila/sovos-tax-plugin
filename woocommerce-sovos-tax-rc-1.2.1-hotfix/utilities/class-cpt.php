<?php
/**
 * Custom Post Types.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\CPT;

class Woo_Sovos_CPT {

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

        // Set post types.
        $post_types = [
            'custom_type'   => [
                'singular'  => 'custom type',
                'plural'    => 'custom types',
                'args'      => [
                    'label'                 => __( 'Custom Type', WOO_SOVOS_DOMAIN ),
                    'description'           => __( 'Custom Type Description', WOO_SOVOS_DOMAIN ),
                    'supports'              => false,
                    'taxonomies'            => [ 'category', 'post_tag' ],
                    'hierarchical'          => false,
                    'public'                => true,
                    'show_ui'               => true,
                    'show_in_menu'          => true,
                    'menu_icon'             => 'dashicons-admin-post',
                    'menu_position'         => 5,
                    'show_in_admin_bar'     => true,
                    'show_in_nav_menus'     => true,
                    'can_export'            => true,
                    'has_archive'           => true,
                    'exclude_from_search'   => false,
                    'publicly_queryable'    => true,
                    'capability_type'       => 'page',
                ],
            ],
        ];

        // Return.
        return $post_types;

    }

    /**
     * Register.
     * 
     * @since   1.0.0
     */
    public function register() {

        // Loop through post types.
        foreach( $this->define() as $post_type => $type ) {

            // Set labels.
            $labels = [
                'name'                  => _x( ucwords( $type['singular'] ), 'Post Type General Name', WOO_SOVOS_DOMAIN ),
                'singular_name'         => _x( ucwords( $type['singular'] ), 'Post Type Singular Name', WOO_SOVOS_DOMAIN ),
                'menu_name'             => __( ucwords( $type['plural'] ), WOO_SOVOS_DOMAIN ),
                'name_admin_bar'        => __( ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'archives'              => __( ucwords( $type['singular'] ) . ' Archives', WOO_SOVOS_DOMAIN ),
                'attributes'            => __( ucwords( $type['singular'] ) . ' Attributes', WOO_SOVOS_DOMAIN ),
                'parent_item_colon'     => __( 'Parent ' . ucwords( $type['singular'] ) . ':', WOO_SOVOS_DOMAIN ),
                'all_items'             => __( 'All ' . ucwords( $type['plural'] ), WOO_SOVOS_DOMAIN ),
                'add_new_item'          => __( 'Add New ' . ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'add_new'               => __( 'Add New', WOO_SOVOS_DOMAIN ),
                'new_item'              => __( 'New ' . ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'edit_item'             => __( 'Edit ' . ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'update_item'           => __( 'Update ' . ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'view_item'             => __( 'View ' . ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'view_items'            => __( 'View ' . ucwords( $type['plural'] ), WOO_SOVOS_DOMAIN ),
                'search_items'          => __( 'Search ' . ucwords( $type['singular'] ), WOO_SOVOS_DOMAIN ),
                'not_found'             => __( 'Not found', WOO_SOVOS_DOMAIN ),
                'not_found_in_trash'    => __( 'Not found in Trash', WOO_SOVOS_DOMAIN ),
                'featured_image'        => __( 'Featured Image', WOO_SOVOS_DOMAIN ),
                'set_featured_image'    => __( 'Set featured image', WOO_SOVOS_DOMAIN ),
                'remove_featured_image' => __( 'Remove featured image', WOO_SOVOS_DOMAIN ),
                'use_featured_image'    => __( 'Use as featured image', WOO_SOVOS_DOMAIN ),
                'insert_into_item'      => __( 'Insert into ' . $type['singular'], WOO_SOVOS_DOMAIN ),
                'uploaded_to_this_item' => __( 'Uploaded to this ' . $type['singular'], WOO_SOVOS_DOMAIN ),
                'items_list'            => __( ucwords( $type['singular'] ) . ' list', WOO_SOVOS_DOMAIN ),
                'items_list_navigation' => __( ucwords( $type['singular'] ) . ' list navigation', WOO_SOVOS_DOMAIN ),
                'filter_items_list'     => __( 'Filter ' . $type['plural'] . ' list', WOO_SOVOS_DOMAIN ),
            ];

            // Add labels. 
            $type['args']['labels'] = $labels;

            // Register post type.
            register_post_type( $post_type, $type['args'] );

        }

    }

}
new Woo_Sovos_CPT();



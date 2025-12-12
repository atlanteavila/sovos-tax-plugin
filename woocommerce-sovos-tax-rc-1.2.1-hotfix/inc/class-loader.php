<?php
/**
 * Loader.
 * 
 * @since       1.0.0
 * @author      Built Mighty
 * @package     Plugin Boilerplate
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS;

class Woo_Sovos_Loader {

    /**
     * Array of actions.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     array
     */
    protected $actions;

    /**
     * Array of filters.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     array
     */
    protected $filters;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Actions and filters.
        $this->actions = [];
        $this->filters = [];

    }

    /**
     * Add an action.
     * 
     * @since   1.0.0
     * @param   string      $hook           The name of the WordPress action that is being registered.
     * @param   object      $component      A reference to the instance of the object on which the action is defined.
     * @param   string      $callback       The name of the function definition on the $component.
     * @param   int         $priority       Optional. The priority at which the function should be fired. Default is 10.
     * @param   int         $accepted_args  Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {

        // Add to actions.
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );

    }

    /**
     * Add a filter.
     * 
     * @since   1.0.0
     * @param   string      $hook           The name of the WordPress filter that is being registered.
     * @param   object      $component      A reference to the instance of the object on which the filter is defined.
     * @param   string      $callback       The name of the function definition on the $component.
     * @param   int         $priority       Optional. The priority at which the function should be fired. Default is 10.
     * @param   int         $accepted_args  Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {

        // Add to filters.
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );

    }

    /**
     * Add to hooks.
     * 
     * @since   1.0.0
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {

        // Add to hooks.
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        ];

        // Return.
        return $hooks;

    }

    /**
     * Register the actions and filters.
     * 
     * @since   1.0.0
     */
    public function run() {

        // Loop through filters.
        foreach( $this->filters as $hook ) {

            // Add filter.
            add_filter( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );

        }

        // Loop through actions.
        foreach( $this->actions as $hook ) {

            // Add action.
            add_action( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );

        }

    }

}

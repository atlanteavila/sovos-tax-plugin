<?php
/**
 * Global Utilities
 * 
 * @package BuiltMighty\WOO_SOVOS\Utility
 * 
 * @since 1.0.0
 * 
 */

if ( ! function_exists( 'debug_log' ) ) :
	/**
	 * Debug Log
	 * 
	 * @param mixed $log - The log to output.
	 * @param string $label - The label to output.
	 * 
	 * @return void
	 * 
	 * @since 1.0.0
	 */
	function debug_log( $log, $label = null ) {
			if ( true !== WP_DEBUG )
				return;

			if ( $label === null ) :
				$backtrace = debug_backtrace();
				$file      = file( $backtrace[0]['file'] );
				$line      = $file[$backtrace[0]['line'] - 1 ];
				preg_match( "/\\((.*?)\\)/", $line, $matches );
				$label = $matches[1];

				if ( isset($backtrace[1] ) ) :
					$class    = isset( $backtrace[1]['class'] ) ?
						$backtrace[1]['class'] :
						'';
					$function = isset( $backtrace[1]['function'] ) ?
						$backtrace[1]['function'] :
						'';
					if ( $class || $function ) :
						if ( $class )
							$function = "$class->$function";

						$label .= " $function";
					endif; // endif ( $class || $function ) :
				endif; // endif ( isset($backtrace[1] ) ) :
			endif; // endif ( $label === null ) :

			error_log( "[$label]" );

			if ( is_array( $log ) || is_object( $log ) ) :
					error_log( print_r( $log, true ) );
			elseif ( is_bool( $log ) ) :
					error_log( $log == true ? 'true' : 'false' );
			elseif ( is_numeric( $log ) ) :
					error_log( $log );
			elseif ($log === null) :
					error_log( 'null' );
			else :
					error_log( $log );
			endif;

			error_log( "[/$label]" );
	}

endif; // endif ( ! function_exists( 'debug_log' ) ) :
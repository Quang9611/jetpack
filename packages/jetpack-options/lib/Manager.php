<?php

namespace Jetpack\V7\Options;

abstract class Manager {

	/**
	 * An array that maps a grouped option type to an option name.
	 * @var array
	 */
	protected $grouped_options = array(
		'compact' => 'jetpack_options',
		'private' => 'jetpack_private_options'
	);

	/**
	 * Returns an array of option names for a given type.
	 *
	 * @param string $type The type of option to return. Defaults to 'compact'.
	 *
	 * @return array
	 */
	abstract public function get_option_names( $type );

	/**
	 * Deletes the given option.  May be passed multiple option names as an array.
	 * Updates jetpack_options and/or deletes jetpack_$name as appropriate.
	 *
	 * @param string|array $names Option names. They must come _without_ `jetpack_%` prefix. The method will prefix the option names.
	 *
	 * @return bool Was the option successfully deleted?
	 */
	public function delete_option( $names ) {
		$result = true;
		$names  = (array) $names;

		if ( ! $this->is_valid( $names ) ) {
			trigger_error( sprintf( 'Invalid Jetpack option names: %s', print_r( $names, 1 ) ), E_USER_WARNING );
			return false;
		}

		foreach ( array_intersect( $names, $this->get_option_names( 'non_compact' ) ) as $name ) {
			if ( self::is_network_option( $name ) ) {
				$result = delete_site_option( "jetpack_$name" );
			} else {
				$result = delete_option( "jetpack_$name" );
			}

		}

		foreach ( array_keys( $this->grouped_options ) as $group ) {
			if ( ! $this->delete_grouped_option( $group, $names ) ) {
				$result = false;
			}
		}

		return $result;
	}

	protected function delete_grouped_option( $group, $names ) {
		$options = get_option( $this->grouped_options[ $group ], array() );

		$to_delete = array_intersect( $names, $this->get_option_names( $group ), array_keys( $options ) );
		if ( $to_delete ) {
			foreach ( $to_delete as $name ) {
				unset( $options[ $name ] );
			}

			return update_option( $this->grouped_options[ $group ], $options );
		}

		return true;
	}

	/**
	 * Is the option name valid?
	 *
	 * @param string      $name  The name of the option
	 * @param string|null $group The name of the group that the option is in. Default to null, which will search non_compact.
	 *
	 * @return bool Is the option name valid?
	 */
	public function is_valid( $name, $group = null ) {
		if ( is_array( $name ) ) {
			$compact_names = array();
			foreach ( array_keys( $this->grouped_options ) as $_group ) {
				$compact_names = array_merge( $compact_names, $this->get_option_names( $_group ) );
			}

			$result = array_diff( $name, $this->get_option_names( 'non_compact' ), $compact_names );

			return empty( $result );
		}

		if ( is_null( $group ) || 'non_compact' === $group ) {
			if ( in_array( $name, $this->get_option_names( $group ) ) ) {
				return true;
			}
		}

		foreach ( array_keys( self::$grouped_options ) as $_group ) {
			if ( is_null( $group ) || $group === $_group ) {
				if ( in_array( $name, $this->get_option_names( $_group ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if an option must be saved for the whole network in WP Multisite
	 *
	 * @param string $option_name Option name. It must come _without_ `jetpack_%` prefix. The method will prefix the option name.
	 *
	 * @return bool
	 */
	public function is_network_option( $option_name ) {
		if ( ! is_multisite() ) {
			return false;
		}
		return in_array( $option_name, $this->get_option_names( 'network' ) );
	}
}

<?php

defined( 'ABSPATH' ) || exit;

abstract class WC_Vipps_Model {
	protected array $required_fields = [];

	public function __construct( array $data ) {
		$this->from_array( $data );
	}

	private function from_array( array $data ): void {
		foreach ( $data as $key => $value ) {
			$snake_case_key = $this->camel_to_snake( $key );
			$func_name      = "set_$snake_case_key";

			if ( ! method_exists( get_class( $this ), $func_name ) || ! $value ) {
				continue;
			}

			$this->{$func_name}( $value );
		}
	}

	private function camel_to_snake( $input ): string {
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) );
	}

	abstract function to_array( $check_required = true ): array;

	protected function set_value( $name, $value, $class = null ): self {
		if ( is_array( $value ) && $class ) {
			$this->{$name} = new $class( $value );
		} else {
			$this->{$name} = $value;
		}

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	protected function check_required( ?string $keyed_by = null ): void {
		$class = get_class( $this );

		$fields = ! $keyed_by ? $this->required_fields : $this->required_fields[ $keyed_by ];

		foreach ( $fields as $value ) {
			if ( ! $this->{$value} ) {
				throw new WC_Vipps_Recurring_Missing_Value_Exception( "Incorrect usage. Required value $value is missing in $class." );
			}
		}
	}

	protected function serialize_value( $value ) {
		if ( $value instanceof DateTime ) {
			$value = $value->format( 'c' );
		}

		if ( is_subclass_of( $value, __CLASS__ ) ) {
			$value = $value->to_array( false );
		}

		return $value;
	}

	protected function conditional( string $name, $value ): array {
		if ( ! $value ) {
			return [];
		}

		$value = $this->serialize_value( $value );

		return [ $name => $value ];
	}
}

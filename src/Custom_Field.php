<?php

namespace Noptin\Connection;

/**
 * This class represents a single custom field.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class represents a single custom field.
 */
class Custom_Field {

	/**
	 * @var string $id The custom field ID.
	 */
	public $id;

	/**
	 * @var string $name The custom field name.
	 */
	public $name;

	/**
	 * @var string $description The custom field description.
	 */
	public $description = '';

	/**
	 * @var string $default The field's default value.
	 */
	public $default = '';

	/**
	 * Class constructor.
	 *
	 * @param arrau $args
	 */
	public function __construct( $args ) {

		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
    }

}

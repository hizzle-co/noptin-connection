<?php

namespace Noptin\Connection\Actions;

/**
 * Removes the contact from a list.
 *
 * @version 0.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes the contact from a list.
 */
class Remove_List extends List_Action {

	/**
	 * @inheritdoc
	 */
	public function get_id() {
		return sprintf(
			'remove-%s-%s',
			$this->remote_id,
			$this->list_type
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_name() {

		if ( $this->is_taggy ) {
			return sprintf(
				/* Translators: %1$s provider tame, %2$s list type. */
				__( '%1$s > Remove %2$s', 'newsletter-optin-box' ),
				$this->remote_name,
				$this->list_name_plural
			);
		}

		return sprintf(
			/* Translators: %1$s provider tame, %2$s list type. */
			__( '%1$s > Remove From %2$s', 'newsletter-optin-box' ),
			$this->remote_name,
			$this->list_name
		);

	}

	/**
	 * @inheritdoc
	 */
	public function get_description() {

		if ( $this->is_taggy ) {
			return sprintf(
				/* Translators: %1$s provider tame, %2$s list type. */
				__( 'Remove %1$s %2$s', 'newsletter-optin-box' ),
				$this->remote_name,
				strtolower( $this->list_name_plural )
			);
		}

		return sprintf(
			/* Translators: %1$s provider tame, %2$s list type. */
			__( 'Remove from %1$s %2$s', 'newsletter-optin-box' ),
			$this->remote_name,
			strtolower( $this->list_name )
		);
	}

	/**
	 * Retrieve the action's settings.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	public function get_settings() {

		$settings    = parent::get_settings();
		$list_object = $this->get_connection()->list_types[ $this->list_type ];

		// Maybe select parent list.
		if ( ! empty( $this->group_type ) ) {
			$groups       = $this->get_parents();
			$group_object = $this->get_connection()->list_types[ $this->group_type ];

			// Select parent list.
			$settings[ $this->group_type ] = array(
				'el'      => 'select',
				'label'   => $this->group_name,
				'options' => $groups,
				'default' => $group_object->get_default_list_id(),
			);

			// Select child list.
			if ( $this->is_taggy ) {
				$settings[ $this->list_type ] = array(
					'el'          => 'text',
					'label'       => $this->list_name_plural,
					'description' => sprintf(
						// translators: %s is the plural form of the list name.
						__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
						strtolower( $this->list_name_plural )
					),
					'default'     => '',
				);
			} else {

				foreach ( array_keys( $groups ) as $group_id ) {
					$settings[ "child_{$group_id}" ] = array(
						'el'       => 'multi_checkbox_alt',
						'label'    => $this->list_name_plural,
						'options'  => $this->get_children( $group_id ),
						'restrict' => esc_attr( $this->group_type ) . "=='" . esc_attr( $group_id ) . "'",
						'default'  => array(),
					);
				}
			}
		} else { // Child lists that are not grouped by the parent list.

			if ( $this->is_taggy ) {
				$settings[ $this->list_type ] = array(
					'el'          => 'text',
					'label'       => $this->list_name_plural,
					'description' => sprintf(
						// translators: %s is the plural form of the list name.
						__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
						strtolower( $this->list_name_plural )
					),
					'default'     => '',
				);
			} else {

				$settings[ $this->list_type ] = array(
					'el'      => 'multi_checkbox_alt',
					'label'   => $this->list_name,
					'options' => $this->get_lists(),
					'default' => $list_object->get_default_list_id(),
				);
			}
		}

		return $settings;
	}

	/**
	 * Removes a contact from a list.
	 *
	 * @since 1.0.0
	 * @param string $email The contact email.
	 * @param string[] $lists The list IDs from which to remove the contact.
	 * @param string $parent_id The parent list ID.
	 * @param array $args Extra arguments.
	 * @return void
	 */
	protected function process( $email, $lists, $parent_id, $args = array() ) {
		do_action( "noptin_remove_{$this->remote_id}_{$this->list_type}_contact", $email, noptin_parse_list( $lists, true ), $parent_id );
	}
}
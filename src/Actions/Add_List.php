<?php

namespace Noptin\Connection\Actions;

/**
 * Adds a contact to a list.
 *
 * @version 0.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a contact to a list.
 */
class Add_List extends List_Action {

	/**
	 * @inheritdoc
	 */
	public function get_id() {
		return sprintf(
			'add-%s-%s',
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
				__( '%1$s > Add %2$s', 'newsletter-optin-box' ),
				$this->remote_name,
				$this->list_name_plural
			);
		}

		return sprintf(
			/* Translators: %1$s provider tame, %2$s list type. */
			__( '%1$s > Add To %2$s', 'newsletter-optin-box' ),
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
				__( 'Add %1$s %2$s', 'newsletter-optin-box' ),
				$this->remote_name,
				strtolower( $this->list_name_plural )
			);
		}

		return sprintf(
			/* Translators: %1$s provider tame, %2$s list type. */
			__( 'Add to %1$s %2$s', 'newsletter-optin-box' ),
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

		$settings          = parent::get_settings();
		$default_list_type = $this->get_connection()->get_default_list_type();
		$list_object       = $this->get_connection()->list_types[ $this->list_type ];

		// If default list type, add setting to select a single list.
		if ( $default_list_type->id === $this->list_type ) {
			$lists = $this->get_lists();

			$settings[ $this->list_type ] = array(
				'type'    => 'select',
				'label'   => $this->list_name,
				'options' => $this->get_lists(),
				'default' => $list_object->get_default_list_id(),
			);

			foreach ( array_keys( $lists ) as $id ) {
				$settings = array_merge(
					$settings,
					$this->get_custom_field_settings( $id, esc_attr( $this->list_type ) . "=='" . esc_attr( $id ) . "'" )
				);
			}
		} elseif ( ! empty( $this->group_type ) ) { // Child lists that are grouped by the parent list.
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

			// Optionally create missing contacts.
			$settings['create_if_not_exists'] = array(
				'type'        => 'checkbox_alt',
				'el'          => 'input',
				'label'       => sprintf(
                    // Translators: %s is the name of the subscriber, E.g, contacts, subscribers, users, etc
                    __( 'Create missing %s', 'newsletter-optin-box' ),
                    $this->subscriber_name_plural
                ),
				'description' => sprintf(
                    // Translators: %s is the name of the subscriber, E.g, contact, subscriber, user, etc
                    __( 'Create a new %1$s if they do not exist in %2$s.', 'newsletter-optin-box' ),
                    $this->subscriber_name,
                    $this->remote_name
                ),
                'default'     => true,
			);

			// Set custom fields.
			foreach ( array_keys( $groups ) as $id ) {
				$settings = array_merge(
					$settings,
					$this->get_custom_field_settings( $id, esc_attr( $this->list_type ) . "=='" . esc_attr( $id ) . "' && create_if_not_exists" )
				);
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
					'el'      => 'select',
					'label'   => $this->list_name,
					'options' => $this->get_lists(),
					'default' => $list_object->get_default_list_id(),
				);
			}
		}

		return $settings;
	}

	/**
	 * Adds a contact to a list.
	 *
	 * @since 1.0.0
	 * @param string $email The contact email.
	 * @param string[] $lists The list IDs from which to remove the contact.
	 * @param string $parent_id The parent list ID.
	 * @param array $args Extra arguments.
	 * @return void
	 */
	protected function process( $email, $lists, $parent_id, $args = array() ) {
		$args[ $this->group_type ] = $parent_id;
		do_action( "noptin_add_{$this->remote_id}_{$this->list_type}_contact", $email, $lists, $args );
	}
}

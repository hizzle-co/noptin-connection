<?php

namespace Noptin\Connection\Actions;

/**
 * Create/Update a contact.
 *
 * @version 0.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create/Update a contact.
 */
class Add_Contact_Action extends Abstract_Action {

	/**
	 * @inheritdoc
	 */
	public function get_id() {
		return sprintf( 'add-%s-contact', $this->remote_id );
	}

	/**
	 * @inheritdoc
	 */
	public function get_name() {

		return sprintf(
			/* Translators: %1$s provider tame, %2$s contact type. */
			__( '%1$s > Create/Update %2$s', 'newsletter-optin-box' ),
			$this->remote_name,
			$this->subscriber_name
		);

	}

	/**
	 * @inheritdoc
	 */
	public function get_description() {

		return sprintf(
			/* Translators: %1$s provider tame, %2$s contact type. */
			__( 'Create or Update %1$s %2$s', 'newsletter-optin-box' ),
			$this->remote_name,
			strtolower( $this->subscriber_name )
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_rule_description( $rule ) {
		$settings = $rule->trigger_settings;

		// Abort if no list type was selected.
		$default_list_type = $this->get_default_list_type();
		if ( empty( $settings[ $default_list_type ] ) ) {
			return $this->get_description();
		}

		$list_id   = $settings[ $default_list_type ];
		$list_type = $this->get_connection()->list_types[ $default_list_type ];
		$all_lists = $list_type->get_lists();
		$list_name = isset( $all_lists[ $list_id ] ) ? $all_lists[ $list_id ] : $list_id;

		return sprintf(
			'%s <p class="description">%s</p>',
			$this->get_description(),
			sprintf(
				'%s: %s',
				esc_html( $list_type->name ),
				esc_html( $list_name )
			)
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
		$connection        = $this->get_connection();
		$default_list_type = $connection->get_default_list_type();
		$parent_lists      = $default_list_type->get_lists();

		// Select main list.
		$settings[ $default_list_type->id ] = array(
			'el'      => 'select',
			'label'   => $default_list_type->name,
			'options' => $parent_lists,
			'default' => $default_list_type->get_default_list_id(),
		);

		// Select child lists.
		foreach ( $connection->list_types as $list_type ) {

			// Skip the default list type.
			if ( $list_type->id === $default_list_type->id ) {
				continue;
			}

			// Skip if has parent and parent is not equal to the default list type.
			if ( ! empty( $list_type->parent_id ) && $list_type->parent_id !== $default_list_type->id ) {
				continue;
			}

			// Taggy lists do not need a parent.
			if ( $list_type->is_taggy ) {
				$settings[ $list_type->id ] = array(
					'el'          => 'input',
					'type'        => 'text',
					'label'       => $list_type->name_plural,
					'description' => sprintf(
						'%s <span v-show="availableSmartTags">%s</span>',
						sprintf(
							// translators: %s is the plural form of the list name.
							__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
							strtolower( $list_type->name_plural )
						),
						sprintf(
							/* translators: %1: Opening link, %2 closing link tag. */
							esc_html__( 'You can use %1$ssmart tags%2$s to enter a dynamic value.', 'newsletter-optin-box' ),
							'<a href="#TB_inline?width=0&height=550&inlineId=noptin-automation-rule-smart-tags" class="thickbox">',
							'</a>'
						)
					),
					'default'     => '',
					'append'      => '<a href="#TB_inline?width=0&height=550&inlineId=noptin-automation-rule-smart-tags" class="thickbox"><span class="dashicons dashicons-shortcode"></span></a>',
				);

				continue;
			}

			// Child lists that are grouped by the parent list.
			if ( ! empty( $list_type->parent_id ) ) {

				foreach ( array_keys( $parent_lists ) as $parent_list_id ) {
					$settings[ "child_{$list_type->id}_{$parent_list_id}" ] = array(
						'el'       => 'multi_checkbox_alt',
						'label'    => $list_type->name_plural,
						'options'  => $list_type->get_lists( $parent_list_id ),
						'restrict' => $this->get_restrict_key( $default_list_type->id ) . "=='" . esc_attr( $parent_list_id ) . "'",
						'default'  => array(),
					);
				}

				continue;
			}

			// Child lists that are not grouped by the parent list.
			$settings[ $list_type->id ] = array(
				'el'      => 'multi_checkbox_alt',
				'label'   => $list_type->name_plural,
				'options' => $list_type->get_lists(),
				'default' => array(),
			);
		}

		// Set custom fields.
		foreach ( array_keys( $parent_lists ) as $parent_list_id ) {
			$settings = array_replace(
				$settings,
				$this->get_custom_field_settings( $parent_list_id, $this->get_restrict_key( $default_list_type->id ) . "=='" . esc_attr( $parent_list_id ) . "'" )
			);
		}

		// Map custom fields.
		if ( $connection->has_universal_fields ) {
			$settings = array_replace(
				$settings,
				$this->get_custom_field_settings( '' )
			);
		} else {
			foreach ( array_keys( $parent_lists ) as $parent_list_id ) {
				$settings = array_replace(
					$settings,
					$this->get_custom_field_settings( $parent_list_id, $this->get_restrict_key( $default_list_type->id ) . "=='" . esc_attr( $parent_list_id ) . "'" )
				);
			}
		}
		return $settings;
	}

	/**
	 * Returns the default list type.
	 *
	 * @return string
	 */
	public function get_default_list_type() {
		$default_list_type = $this->get_connection()->get_default_list_type();
		return $default_list_type->id;
	}

	/**
	 * Returns the selected lists.
	 *
	 * @since 0.0.1
	 * @param array $settings The action settings.
	 * @return array
	 */
	public function get_selected_lists( $settings ) {

		$lists = array();

		$default_list_type = $this->get_default_list_type();

		// Abort if default list type is not specified.
		if ( empty( $settings[ $default_list_type ] ) ) {
			return $lists;
		}

		$lists[ $default_list_type ] = $settings[ $default_list_type ];

		// Set child lists.
		foreach ( $this->get_connection()->list_types as $list_type ) {

			// Skip the default list type.
			if ( $list_type->id === $default_list_type ) {
				continue;
			}

			// Skip if has parent and parent is not equal to the default list type.
			if ( ! empty( $list_type->parent_id ) && $list_type->parent_id !== $default_list_type ) {
				continue;
			}

			if ( $list_type->is_taggy || empty( $list_type->parent_id ) ) {
				if ( ! empty( $settings[ $list_type->id ] ) ) {
					$lists[ $list_type->id ] = noptin_parse_list( $settings[ $list_type->id ], true );
				}
				continue;
			}

			$key = 'child_' . $list_type->id . '_' . $settings[ $default_list_type ];
			if ( ! empty( $settings[ $key ] ) ) {
				$lists[ $list_type->id ] = noptin_parse_list( $settings[ $key ], true );
			}
		}

		return $lists;
	}

	/**
	 * Returns whether or not the action can run (dependancies are installed).
	 *
	 * @since 0.0.1
	 * @param mixed $subject The subject.
	 * @param Noptin_Automation_Rule $rule The automation rule used to trigger the action.
	 * @param array $args Extra arguments passed to the action.
	 * @return bool
	 */
	public function can_run( $subject, $rule, $args ) {

		if ( false === parent::can_run( $subject, $rule, $args ) ) {
			return false;
		}

		// Abort if default list type is not specified.
		return ! empty( $rule->action_settings[ $this->get_default_list_type() ] );

	}

	/**
	 * Create/Update a contact
	 *
	 * @since 1.0.0
	 * @param mixed $subject The subject.
	 * @param \Noptin_Automation_Rule $rule The automation rule used to trigger the action.
	 * @param array $args Extra arguments passed to the action.
	 * @return void
	 */
	public function run( $subject, $rule, $args ) {

		// Fetch the contact email.
		$email = $this->get_subject_email( $subject, $rule, $args );

		// Prepare contact args.
		$contact_args = array();

		// Add selected lists.
		foreach ( $this->get_selected_lists( $rule->action_settings ) as $list_type => $list_ids ) {

			if ( is_string( $list_ids ) ) {
				$contact_args[ $list_type ] = $args['smart_tags']->replace_in_text_field( $list_ids );
			} else {
				$contact_args[ $list_type ] = array_map( array( $args['smart_tags'], 'replace_in_text_field' ), $list_ids );
			}
		}

		// Custom fields.
		$contact_args['custom_fields'] = $this->get_custom_fields(
			$contact_args[ $this->get_default_list_type() ],
			$rule,
			$args
		);

		// Create / Update the contact.
		$this->get_connection()->process_contact( $email, $contact_args );
	}

}


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
		$connection        = $this->get_connection();
		$default_list_type = $connection->get_default_list_type();
		$list_object       = $connection->list_types[ $this->list_type ];

		// If the connection supports universal contacts...
		if ( $connection->has_universal_contacts ) {

			if ( $this->is_taggy ) {
				$settings[ $this->list_type ] = array(
					'el'          => 'input',
					'type'        => 'text',
					'label'       => $this->list_name_plural,
					'description' => sprintf(
						'%s <span v-show="availableSmartTags">%s</span>',
						sprintf(
							// translators: %s is the plural form of the list name.
							__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
							strtolower( $this->list_name_plural )
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
			} else {

				$settings[ $this->list_type ] = array(
					'el'          => 'multi_checkbox_alt',
					'label'       => $this->list_name_plural,
					'options'     => $this->get_children( '' ),
					'default'     => array(),
				);
			}

			// Optionally create missing contacts.
			if ( $connection->has_universal_fields ) {

				$settings['create_if_not_exists'] = array(
					'type'        => 'checkbox_alt',
					'el'          => 'input',
					'label'       => '&nbsp;',
					'description' => sprintf(
						// Translators: %s is the name of the subscriber, E.g, contact, subscriber, user, etc
						__( 'Create a new %1$s if they do not exist in %2$s.', 'newsletter-optin-box' ),
						$this->subscriber_name,
						$this->remote_name
					),
					'default'     => false,
				);

				// Map custom fields.
				$settings = array_replace(
					$settings,
					$this->get_custom_field_settings( '', $this->get_restrict_key( 'create_if_not_exists' ) )
				);
			}
		} elseif ( $default_list_type->id === $this->list_type ) { // If default list type, add setting to select a single list.
			$lists = $this->get_lists();

			if ( $this->is_taggy ) {
				$settings[ $this->list_type ] = array(
					'el'          => 'input',
					'type'        => 'text',
					'label'       => $this->list_name_plural,
					'description' => sprintf(
						'%s <span v-show="availableSmartTags">%s</span>',
						sprintf(
							// translators: %s is the plural form of the list name.
							__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
							strtolower( $this->list_name_plural )
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
			} else {

				$settings[ $this->list_type ] = array(
					'el'      => 'select',
					'label'   => $this->list_name,
					'options' => $this->get_lists(),
					'default' => $list_object->get_default_list_id(),
				);
			}

			// Map custom fields.
			if ( $connection->has_universal_fields ) {
				$settings = array_replace(
					$settings,
					$this->get_custom_field_settings( '' )
				);
			} else {
				foreach ( array_keys( $lists ) as $id ) {
					$settings = array_replace(
						$settings,
						$this->get_custom_field_settings( $id, $this->get_restrict_key( $this->list_type ) . "=='" . esc_attr( $id ) . "'" )
					);
				}
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
					'el'          => 'input',
					'type'        => 'text',
					'label'       => $this->list_name_plural,
					'description' => sprintf(
						'%s <span v-show="availableSmartTags">%s</span>',
						sprintf(
							// translators: %s is the plural form of the list name.
							__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
							strtolower( $this->list_name_plural )
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
			} else {

				foreach ( array_keys( $groups ) as $group_id ) {
					$settings[ "child_{$group_id}" ] = array(
						'el'       => 'multi_checkbox_alt',
						'label'    => $this->list_name_plural,
						'options'  => $this->get_children( $group_id ),
						'restrict' => $this->get_restrict_key( $this->group_type ) . "=='" . esc_attr( $group_id ) . "'",
						'default'  => array(),
					);
				}
			}

			// Optionally create missing contacts.
			$settings['create_if_not_exists'] = array(
				'type'        => 'checkbox_alt',
				'el'          => 'input',
				'label'       => '&nbsp;',
				'description' => sprintf(
                    // Translators: %s is the name of the subscriber, E.g, contact, subscriber, user, etc
                    __( 'Create a new %1$s if they do not exist in %2$s.', 'newsletter-optin-box' ),
                    $this->subscriber_name,
                    $this->remote_name
                ),
                'default'     => true,
			);

			// Map custom fields.
			if ( $connection->has_universal_fields ) {
				$settings = array_replace(
					$settings,
					$this->get_custom_field_settings( '', $this->get_restrict_key( 'create_if_not_exists' ) )
				);
			} else {
				foreach ( array_keys( $groups ) as $id ) {
					$settings = array_replace(
						$settings,
						$this->get_custom_field_settings( $id, $this->get_restrict_key( $this->group_type ) . "=='" . esc_attr( $id ) . "' && " . $this->get_restrict_key( 'create_if_not_exists' ) )
					);
				}
			}
		} else { // Child lists that are not grouped by the parent list.

			if ( $this->is_taggy ) {
				$settings[ $this->list_type ] = array(
					'el'          => 'input',
					'type'        => 'text',
					'label'       => $this->list_name_plural,
					'description' => sprintf(
						'%s <span v-show="availableSmartTags">%s</span>',
						sprintf(
							// translators: %s is the plural form of the list name.
							__( 'Enter a comma separated list of %s.', 'newsletter-optin-box' ),
							strtolower( $this->list_name_plural )
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

		if ( ! empty( $this->group_type ) ) {
			$args[ $this->group_type ] = $parent_id;
		}

		do_action( "noptin_add_{$this->remote_id}_{$this->list_type}_contact", $email, $lists, $args );
	}
}

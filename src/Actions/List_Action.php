<?php

namespace Noptin\Connection\Actions;

/**
 * Handles individual list actions.
 *
 * @version 0.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles individual list actions.
 */
abstract class List_Action extends Abstract_Action {

	/**
	 * @var bool Whether this list type is taggy.
	 */
	protected $is_taggy = false;

	/**
	 * @var string $group_type in case the lists are grouped by type.
	 */
	protected $group_type;

	/**
	 * @var string $group_name in case the lists are grouped by type.
	 */
	protected $group_name;

	/**
	 * @var string $group_name_plural in case the lists are grouped by type.
	 */
	protected $group_name_plural;

	/**
	 * @var string $list_type E.g, list, audience, group, etc.
	 */
	protected $list_type = 'list';

	/**
	 * @var string $list_name E.g, list, audience, group, etc.
	 */
	protected $list_name;

	/**
	 * @var string $list_name_plural E.g, lists, audiences, groups, etc.
	 */
	protected $list_name_plural;

	/**
	 * @inheritdoc
	 */
	public function get_rule_description( $rule ) {
		$settings = $rule->trigger_settings;

		// In cases where the lists are grouped by type.
		if ( ! empty( $this->group_type ) ) {

			// Abort if a group was not specified.
			if ( empty( $settings[ $this->group_type ] ) ) {
				return $this->get_description();
			}

			$groups     = $this->get_parents();
			$group_id   = $settings[ $this->group_type ];
			$group_name = isset( $groups[ $group_id ] ) ? $groups[ $group_id ] : $group_id;

			// Check if we have a list for this group.
			if ( empty( $settings[ $group_id ] ) ) {
				return sprintf(
					'%s <p class="description">%s</p>',
					$this->get_description(),
					sprintf( '%s: %s', esc_html( $this->group_name ), esc_html( $group_name ) )
				);
			}

			$lists = $settings[ $group_id ];

			if ( $this->is_taggy ) {
				$list_names = $lists;
				$plural     = 1 < count( noptin_parse_list( $list_names, 1 ) );
			} else {
				$all_lists  = $this->get_children( $group_id );
				$list_names = array();

				foreach ( noptin_parse_list( $lists, 1 ) as $list_id ) {
					$list_names[] = isset( $all_lists[ $list_id ] ) ? $all_lists[ $list_id ] : $list_id;
				}

				$plural     = 1 < count( $list_names );
				$list_names = implode( ', ', $list_names );
			}

			return sprintf(
				'%s <p class="description">%s</p>',
				$this->get_description(),
				sprintf(
					'%s: %s, %s: %s',
					esc_html( $this->group_name ),
					esc_html( $group_name ),
					esc_html( $plural ? $this->list_name_plural : $this->list_name ),
					esc_html( $list_names )
				)
			);
		} else {

			if ( empty( $settings[ $this->list_type ] ) ) {
				return $this->get_description();
			}

			$list = $settings[ $this->list_type ];

			if ( $this->is_taggy ) {
				$list_name = $list;
				$plural    = 1 < count( noptin_parse_list( $list, 1 ) );
			} else {
				$lists     = $this->get_lists();
				$list_name = isset( $lists[ $list ] ) ? $lists[ $list ] : $list;
				$plural    = false;
			}

			return sprintf(
				'%s <p class="description">%s</p>',
				$this->get_description(),
				sprintf(
					'%s: %s',
					esc_html( $plural ? $this->list_name_plural : $this->list_name ),
					esc_html( $list_name )
				)
			);
		}

	}

	/**
	 * Retrieves an array of all lists.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	public function get_lists() {
		return apply_filters( "noptin_get_{$this->remote_id}_{$this->list_type}_options", array(), $this );
	}

	/**
	 * Retrieves an array of parent children.
	 *
	 * @since 0.0.1
	 * @param string $parent_id The parent ID.
	 * @return array
	 */
	public function get_children( $parent_id ) {
		return apply_filters( "noptin_get_{$this->remote_id}_{$this->list_type}_options", array(), $parent_id, $this );
	}

	/**
	 * Retrieves an array of parents by which lists are grouped.
	 *
	 * @since 0.0.1
	 * @param string $parent_id The parent ID.
	 * @return array
	 */
	public function get_parents() {
		return apply_filters( "noptin_get_{$this->remote_id}_{$this->group_type}_options", array(), $this );
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

		list( $group, $list ) = $this->get_list_and_group( $rule->action_settings );

		// In cases where the lists are grouped by type.
		if ( ! empty( $this->group_type ) ) {
			return ! empty( $group ) && ! empty( $list );
		}

		return ! empty( $list );
	}

	/**
	 * Returns the action setting parents and children.
	 *
	 * @since 0.0.1
	 * @param array $settings The action settings.
	 * @return array
	 */
	public function get_list_and_group( $settings ) {

		$parent   = '';
		$children = '';

		// In cases where the lists are grouped by type.
		if ( ! empty( $this->group_type ) ) {

			// Provide parent.
			if ( ! empty( $settings[ $this->group_type ] ) ) {
				$parent = $settings[ $this->group_type ];
			}

			// Taggy lists.
			if ( $this->is_taggy && ! empty( $settings[ $this->list_type ] ) ) {
				$children = $settings[ $this->list_type ];
			}

			// Check if we have a list for this group.
			if ( ! $this->is_taggy && $parent && ! empty( $settings[ "{child_$parent}" ] ) ) {
				$children = $settings[ "{child_$parent}" ];
			}
		} else {

			if ( ! empty( $settings[ $this->list_type ] ) ) {
				$children = $settings[ $this->list_type ];
			}
		}

		return array( $parent, $children );
	}

	/**
	 * Adds / removes a contact from a list.
	 *
	 * @since 1.0.0
	 * @param mixed $subject The subject.
	 * @param \Noptin_Automation_Rule $rule The automation rule used to trigger the action.
	 * @param array $args Extra arguments passed to the action.
	 * @return void
	 */
	public function run( $subject, $rule, $args ) {

		// Fetch the parent and child lists.
		list( $group, $list ) = $this->get_list_and_group( $rule->action_settings );

		// Fetch the contact email.
		$email = $this->get_subject_email( $subject, $rule, $args );

		// Should we create missing contacts?
		$create_missing_contacts = ! empty( $rule->action_settings['create_contact'] );

		// Custom fields.
		$custom_fields = array();

		if ( $create_missing_contacts ) {

			foreach ( $rule->action_settings as $key => $value ) {
				if ( 'custom_field_' === substr( $key, 0, 13 ) ) {
					$custom_fields[ substr( $key, 13 ) ] = $args['smart_tags']->replace_in_text_field( $value );
				}
			}
		}

		// Add the contact to the list.
		$this->process( $email, $list, $group, $create_missing_contacts, $custom_fields );
	}

	/**
	 * Processes a contact.
	 *
	 * @since 1.0.0
	 * @param string $email The contact email.
	 * @param string[] $lists The list IDs from which to remove the contact.
	 * @param string $parent_id The parent list ID.
	 * @param array $args Extra arguments.
	 * @return void
	 */
	abstract protected function process( $email, $lists, $parent_id, $args = array() );

}
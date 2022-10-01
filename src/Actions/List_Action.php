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

		// Fetch the parent and child lists.
		list( $group, $lists ) = $this->get_list_and_group( $rule->action_settings );

		// In cases where the lists are grouped by type.
		if ( ! empty( $this->group_type ) ) {

			// Abort if a group was not specified.
			if ( empty( $group ) ) {
				return $this->get_description();
			}

			$groups     = $this->get_parents();
			$group_name = isset( $groups[ $group ] ) ? $groups[ $group ] : $group;

			// Check if we have a child list for this group.
			if ( empty( $lists ) ) {
				return sprintf(
					'%s <p class="description">%s</p>',
					$this->get_description(),
					sprintf( '%s: <code>%s</code>', esc_html( $this->group_name ), esc_html( $group_name ) )
				);
			}

			if ( $this->is_taggy ) {
				$list_names = $lists;
				$plural     = 1 < count( noptin_parse_list( $list_names, 1 ) );
			} else {
				$all_lists  = $this->get_children( $group );
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
					'%s: <code>%s</code>, %s: <code>%s</code>',
					esc_html( $this->group_name ),
					esc_html( $group_name ),
					esc_html( $plural ? $this->list_name_plural : $this->list_name ),
					esc_html( $list_names )
				)
			);
		} else {

			if ( empty( $lists ) ) {
				return $this->get_description();
			}

			if ( $this->is_taggy ) {
				$list_name = $lists;
				$plural    = 1 < count( noptin_parse_list( $lists, 1 ) );
			} else {
				$all_lists = $this->get_lists();
				$list_name = array();

				foreach ( noptin_parse_list( $lists ) as $list_id ) {
					$list_name[] = isset( $all_lists[ $list_id ] ) ? $all_lists[ $list_id ] : $list_id;
				}

				$plural    = 1 < count( $list_name );
				$list_name = implode( ', ', $list_name );
			}

			return sprintf(
				'%s <p class="description">%s</p>',
				$this->get_description(),
				sprintf(
					'%s: <code>%s</code>',
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

		// If default list type, we'll have no parent.
		if ( empty( $this->group_type ) ) {
			if ( ! empty( $settings[ $this->list_type ] ) ) {
				$children = $settings[ $this->list_type ];
			}
		} else { // Child lists that are grouped by the parent list.

			// Set parent.
			if ( ! empty( $settings[ $this->group_type ] ) ) {
				$parent = $settings[ $this->group_type ];
			}

			// Set child.
			if ( $this->is_taggy ) {
				if ( ! empty( $settings[ $this->list_type ] ) ) {
					$children = $settings[ $this->list_type ];
				}
			} else if ( ! empty( $parent ) && ! empty( $settings[ "child_{$parent}" ] ) ) {
				$children = $settings[ "child_{$parent}" ];
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

		// Convert list to array.
		$list = noptin_parse_list( $list, true );

		// Handle list smart tags.
		$list = array_map( array( $args['smart_tags'], 'replace_in_text_field' ), $list );

		// Fetch the contact email.
		$email = $this->get_subject_email( $subject, $rule, $args );

		// Should we create missing contacts?
		$create_if_not_exists = ! empty( $rule->action_settings['create_if_not_exists'] );

		// Custom fields.
		$custom_fields = $this->get_custom_fields( empty( $group ) ? current( $list ) : $group, $rule, $args );

		// Add the contact to the list.
		$this->process( $email, $list, $group, compact( 'create_if_not_exists', 'custom_fields' ) );
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

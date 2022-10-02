<?php

namespace Noptin\Connection;

/**
 * This class represents a single list type.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class represents a single list type.
 */
abstract class List_Type {

	/**
	 * @var string $id The list type id.
	 */
	public $id;

	/**
	 * @var string $id The list type parent id.
	 */
	public $parent_id;

	/**
	 * @var string $id The parent id for which this list type is usable.
	 */
	public $if_parent;

	/**
	 * @var string $name The list type name.
	 */
	public $name;

	/**
	 * @var string $name_plural The list type name in plural.
	 */
	public $name_plural;

	/**
	 * @var bool $is_taggy Whether this list type is taggy.
	 */
	public $is_taggy = false;

	/**
	 * @var bool $can_filter_campaigns Whether this list type can be used to filter campaign recipients.
	 */
	public $can_filter_campaigns = false;

	/**
	 * @var Connection The connection instance.
	 */
	public $connection;

	/**
	 * Class constructor.
	 *
	 * @param Connection $connection
	 */
	public function __construct( $connection ) {

		$this->connection = $connection;

		// Automation rules.
		add_action( 'noptin_automation_rules_load', array( $this, 'load_automation_rules' ) );

		// Fetch lists.
		add_filter( "noptin_get_{$connection->slug}_{$this->id}_options", array( $this, 'filter_lists' ), 10, 2 );

		// Add contact.
		add_action( "noptin_add_{$connection->slug}_{$this->id}_contact", array( $this, 'add_contact' ), 10, 3 );

		// Remove contact.
		add_action( "noptin_remove_{$connection->slug}_{$this->id}_contact", array( $this, 'remove_contact' ), 10, 3 );
    }

	/**
	 * Registers automation rules.
	 *
	 * @param \Noptin_Automation_Rules $rules
	 */
	public function load_automation_rules( $rules ) {

		$args = array(
			'list_type'              => $this->id,
			'is_taggy'               => $this->is_taggy,
			'list_name'              => $this->name,
			'list_name_plural'       => $this->name_plural,
			'remote_id'              => $this->connection->slug,
			'remote_name'            => $this->connection->name,
			'subscriber_name'        => $this->connection->subscriber_name,
			'subscriber_name_plural' => $this->connection->subscriber_name_plural,
		);

		if ( ! empty( $this->parent_id ) && isset( $this->connection->list_types[ $this->parent_id ] ) ) {
			$args['group_type']        = $this->parent_id;
			$args['group_name']        = $this->connection->list_types[ $this->parent_id ]->name;
			$args['group_name_plural'] = $this->connection->list_types[ $this->parent_id ]->name_plural;
		}

		// Prepare the action args.
		$args = apply_filters( "noptin_{$this->connection->slug}_automation_rule_action_args", $args, $this );

		// Add contacts to the list.
		$rules->add_action( new Actions\Add_List( $args ) );

		// Remove contacts from the list.
		$rules->add_action( new Actions\Remove_List( $args ) );
	}

	/**
     * Empties the cache.
     *
     */
    public function empty_cache() {
		delete_transient( $this->get_cache_key() );
	}

	/**
	 * Retrieves the cache key.
	 *
	 * @param string $post_fix The post fix.
	 * @return string
	 */
	public function get_cache_key( $post_fix = '' ) {
		return "noptin_{$this->connection->slug}_{$this->id}{$post_fix}";
	}

	/**
	 * Returns the default list ID.
	 *
	 * @return string
	 */
	public function get_default_list_id() {
		$default_value = get_noptin_option( "noptin_{$this->connection->slug}_default_{$this->id}", '' );

		if ( empty( $default_value ) && ! $this->is_taggy && empty( $this->parent_id ) ) {
			$default_value = current( array_keys( $this->get_lists() ) );
		}

		return empty( $default_value ) ? '' : $default_value;
	}

	/**
	 * Filters the lists.
	 *
	 * @param array $lists
	 * @param string $parent_id
	 */
	public function filter_lists( $lists, $parent_id ) {
		$parent_id = is_string( $parent_id ) ? trim( $parent_id ) : '';
		return array_replace( $this->get_lists( $parent_id ), (array) $lists );
	}

	/**
	 * Retrieves an array of all lists of this type.
	 *
	 * @param string $group_id The group id for lists that are grouped.
	 * @param bool $force_refresh Whether to force a refresh.
	 * @return array
	 */
	public function get_lists( $group_id = '', $force_refresh = false ) {

		// Get the cache.
		$lists = get_transient( $this->get_cache_key( $group_id ) );

		// If we have a cache, return it.
		if ( false !== $lists && ! $force_refresh ) {
			return $lists;
		}

		// Get the lists.
		$lists = $this->fetch_lists( $group_id );

		// Cache the lists.
		set_transient( $this->get_cache_key( $group_id ), $lists, 12 * HOUR_IN_SECONDS );

		return $lists;
	}

	/**
	 * Fetches the lists from the remote service.
	 */
	protected function fetch_lists( $group_id ) {
		return array();
	}

	/**
     * Adds a contact to the lists.
     *
	 * @param string $email The contact's email address.
	 * @param array $lists The lists to add the contact to.
	 * @param array $args Extra arguments.
     */
    abstract public function add_contact( $email, $lists, $args = array() );

	/**
	 * Removes the given email address from the specified lists.
	 *
	 * @param string $email The email address to remove.
	 * @param array $lists The lists to remove the email address from.
	 * @param string $parent_id The parent list ID.
	 * @return bool
	 */
	abstract public function remove_contact( $email, $lists, $parent_id = '' );
}

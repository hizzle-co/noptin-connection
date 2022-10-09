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
class Remove_Contact_Action extends Abstract_Action {

	/**
	 * @inheritdoc
	 */
	public function get_id() {
		return sprintf( 'remove-%s-contact', $this->remote_id );
	}

	/**
	 * @inheritdoc
	 */
	public function get_name() {

		return sprintf(
			/* Translators: %1$s provider tame, %2$s contact type. */
			__( '%1$s > Remove %2$s', 'newsletter-optin-box' ),
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
			__( 'Remove %1$s %2$s', 'newsletter-optin-box' ),
			$this->remote_name,
			strtolower( $this->subscriber_name )
		);
	}

	/**
	 * Remove a contact
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

		// Remove the contact.
		$this->get_connection()->remove_contact( $email );
	}

}

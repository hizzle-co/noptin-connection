<?php

namespace Noptin\Connection\Actions;

/**
 * All connection based actions should extend this class.
 *
 * @version 0.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main connection action class.
 */
abstract class Abstract_Action extends \Noptin_Abstract_Action {

    /**
     * @var string $subscriber_name E.g, contact, subscriber, user, etc.
     */
    protected $subscriber_name;

    /**
     * @var string $subscriber_name_plural E.g, contacts, subscribers, users, etc.
     */
    protected $subscriber_name_plural;

    /**
     * @var string $remote_name E.g, Mailchimp.
     */
    protected $remote_name;

    /**
     * @var string $remote_id E.g, mailchimp.
     */
    protected $remote_id;

    /**
     * Class constructor.
     *
     * @since 0.0.1
     * @param array $args
     */
    public function __construct( $args = array() ) {

        foreach ( $args as $key => $value ) {
            $this->$key = $value;
        }
    }

	/**
	 * Returns the current connection.
	 *
	 * @return \Noptin\Connection\Connection
	 */
	public function get_connection() {
		return noptin()->integrations->integrations[ $this->remote_id ];
	}

	/**
	 * Returns custom field settings.
	 *
	 * @param string $list_id The list id.
	 * @param string $restrict The custom restrictions.
	 * @return array
	 */
	protected function get_custom_field_settings( $list_id, $restrict = '' ) {

		$settings      = array();
		$_list_id      = empty( $list_id ) ? 'default' : $list_id;
		$prefix        = "custom_field_{$_list_id}_";
		$custom_fields = $this->get_connection()->get_custom_fields( $list_id );

		if ( empty( $custom_fields ) ) {
			return $settings;
		}

		$settings[ $this->remote_id . '_map_custom_fields_heading' ] = array(
			'el'      => 'hero',
			'content' => sprintf(
				/* translators: %s: The remote name. */
				__( 'Map %s custom fields', 'newsletter-optin-box' ),
				$this->remote_name
			),
		);

		foreach ( $custom_fields as $custom_field ) {

			$settings[ "{$prefix}{$custom_field->id}" ] = array(
				'type'        => 'text',
				'el'          => 'input',
				'label'       => $custom_field->name,
				'description' => $custom_field->description,
				'default'     => $custom_field->default,
				'placeholder' => sprintf(
					/* translators: %s: The field name. */
					__( 'Enter %s', 'newsletter-optin-box' ),
					$custom_field->name
				),
				'description' => sprintf(
					'%s<p class="description" v-show="availableSmartTags">%s</p>',
					wp_kses_post( $custom_field->description ),
					sprintf(
						/* translators: %1: Opening link, %2 closing link tag. */
						esc_html__( 'Enter a value or %1$suse smart tags%2$s to map a dynamic value.', 'newsletter-optin-box' ),
						'<a href="#TB_inline?width=0&height=550&inlineId=noptin-automation-rule-smart-tags" class="thickbox">',
						'</a>'
					)
				),
				'append'      => '<a href="#TB_inline?width=0&height=550&inlineId=noptin-automation-rule-smart-tags" class="thickbox"><span class="dashicons dashicons-shortcode"></span></a>',
				'restrict'    => $restrict,
			);

		}

		return $settings;

	}

	/**
	 * Returns the restrict key.
	 *
	 * @param string $raw_key The raw key.
	 */
	protected function get_restrict_key( $raw_key ) {
		return "action_settings['" . esc_attr( $raw_key ) . "']";
	}

	/**
	 * Fetches the custom fields for the given list.
	 *
	 * @param string $list_id The list ID.
	 * @param Noptin_Automation_Rule $rule The automation rule used to trigger the action.
	 * @param array $args Extra arguments passed to the action.
	 * @return array
	 */
	public function get_custom_fields( $list_id, $rule, $args ) {

		$custom_fields = array();
		$list_id       = empty( $list_id ) ? 'default' : $list_id;
		$prefix        = "custom_field_{$list_id}_";
		$prefix_length = strlen( $prefix );

		foreach ( $rule->action_settings as $key => $value ) {

			if ( 0 !== strpos( $key, $prefix ) || '' === $value ) {
				continue;
			}

			$value = is_scalar( $value ) ? $args['smart_tags']->replace_in_text_field( $value ) : $value;

			$custom_fields[ substr( $key, $prefix_length ) ] = $value;
		}

		return $custom_fields;
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

		// Abort if we have no contact.
		return false !== $this->get_subject_email( $subject, $rule, $args );
	}
}

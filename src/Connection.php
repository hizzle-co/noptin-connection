<?php

namespace Noptin\Connection;

/**
 * This class represents a single connection.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class represents a single connection.
 */
abstract class Connection extends \Noptin_Abstract_Integration {

	/**
	 * @var int The priority for hooks.
	 */
	public $priority = 100;

	/**
	 * @var bool Whether this connection is oauth based.
	 */
	public $is_oauth = false;

	/**
	 * @var bool Whether this connection supports double optin.
	 */
	public $double_optin = false;

	/**
	 * @var string type of integration.
	 */
	public $integration_type = 'esp';

	/**
	 * @var string last error message.
	 */
	public $last_error = '';

	/**
	 * @var List_Type[] Supported list types.
	 */
	public $list_types = array();

	/**
	 * @var string $subscriber_name E.g, contact, subscriber, user, etc.
	 */
	public $subscriber_name;

	/**
	 * @var string $subscriber_name_plural E.g, contacts, subscribers, users, etc.
	 */
	public $subscriber_name_plural;

	/**
	 * @var bool Does this connection have universal fields?
	 */
	public $has_universal_fields = false;

	/**
	 * @var bool Does this connection require contacts to belong to a list?
	 */
	public $has_universal_contacts = false;

	/**
     * Initializes the connection.
     */
    public function before_initialize() {

        // Redirect to the connection settings page on install.
		add_action( 'admin_init', array( $this, 'activation_redirect' ), $this->priority );

		// Clear cache when settings are saved.
		add_action( 'noptin_admin_save_options', array( $this, 'empty_cache' ), $this->priority );

		// Debug mode.
		if ( $this->is_debug_mode() ) {
			\Hizzle\Logger\Logger::get_instance();
			add_filter( 'hizzle_logger_admin_show_menu', '__return_true' );
		}

		// Oauth connections.
		if ( $this->is_oauth ) {
			add_action( 'noptin_connect_' . $this->slug, array( $this, 'oauth_connect' ), $this->priority );
			add_action( 'noptin_disconnect_' . $this->slug, array( $this, 'oauth_disconnect' ), $this->priority );
		}
    }

	/**
	 * This method is called after an integration is initialized.
	 *
	 */
	public function initialize() {

		// Register integration.
		add_filter( 'noptin_connection_integrations', array( $this, 'register' ), $this->priority );
		add_filter( 'noptin_upsell_integrations', '__return_false', $this->priority );
		add_filter( 'noptin_com_helper_premium_addons', array( $this, 'register_premium_addon' ), $this->priority );

		// Forms.
		add_action( 'noptin_form_available_integration_settings', array( $this, 'display_form_options' ), $this->priority );

		// Abort if the connection is not enabled.
		if ( ! $this->is_connected() ) {
			return;
		}

		// New subscribers.
		add_action( 'noptin_insert_subscriber', array( $this, 'add_contact' ), $this->priority, 2 );
		add_filter( 'noptin_submitted_data_add_connections', array( $this, 'add_connection_details_to_submitted' ), $this->priority, 2 );

		// Integration specific settings.
		add_filter( 'noptin_single_integration_settings', array( $this, 'add_list_options' ), $this->priority, 3 );

		// Automation rules.
		add_action( 'noptin_automation_rules_load', array( $this, 'register_automation_rules' ) );

		// Map subscriber fields to customer fields.
		add_action( 'noptin_custom_field_settings', array( $this, 'map_contact_to_custom_fields' ), $this->priority );

		// Legacy forms.
		add_filter( 'noptin_optin_form_default_form_state', array( $this, 'add_custom_state_to_legacy_forms' ), $this->priority );
		add_filter( 'default_noptin_shortcode_atts', array( $this, 'add_custom_state_to_legacy_forms' ), $this->priority );
		add_filter( 'noptin_optin_form_editor_sidebar_section', array( $this, 'add_custom_fields_to_legacy_forms_editor' ), $this->priority );
	}

	/**
	 * Registers the connection.
	 *
	 * @param array $types
	 * @return array
	 */
	public function register( $types ) {
		$types[ $this->id ] = $this;
		return $types;
	}

	/**
	 * Registers premium addons.
	 *
	 * @param string[] $addons
	 */
	public function register_premium_addon( $addons ) {
		$addons[] = $this->name;
		return $addons;
	}

	/**
	 * Returns true if the connection is connected.
	 *
	 * @return bool
	 */
	abstract public function is_connected();

	/**
	 * Registers automation rules.
	 *
	 * @param \Noptin_Automation_Rules $rules
	 */
	public function register_automation_rules( $rules ) {

		$rules->add_action(
			new \Noptin\Connection\Actions\Add_Contact_Action(
				array(
					'subscriber_name'        => $this->subscriber_name,
					'subscriber_name_plural' => $this->subscriber_name_plural,
					'remote_name'            => $this->name,
					'remote_id'              => $this->slug,
				)
			)
		);

	}

	/**
	 * Redirect to the connection settings page on install.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function activation_redirect() {

		$redirected = get_option( "noptin_{$this->slug}_redirected" );

		if ( ! empty( $redirected ) || isset( $_GET['activate-multi'] ) || is_network_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Enable integration.
		update_noptin_option( $this->get_enable_integration_option_name(), true );

		update_option( "noptin_{$this->slug}_redirected", 1 );

		wp_safe_redirect( $this->get_settings_url() );
		exit;

	}

	/**
	 * Returns the connection settings.
	 *
	 * @since 1.0.0
	 * @param array $_options Current Noptin settings.
	 * @return array
	 */
	public function add_options( $_options ) {

		$slug    = $this->slug;
		$options = $this->add_enable_integration_option( array() );

		// Debug mode checkbox.
		if ( $this->is_connected() ) {
			$options[ "noptin_{$slug}_debug_mode" ] = array(
				'type'        => 'checkbox_alt',
				'el'          => 'input',
				'section'	  => 'integrations',
				'label'       => __( 'Enable debug mode', 'newsletter-optin-box' ),
				'description' => sprintf(
					'%s <a href="%s" v-if="%s" target="_blank">&nbsp;%s</a>',
					__( 'Enable debug mode to log all API requests and responses.', 'newsletter-optin-box' ),
					esc_url( admin_url( 'tools.php?page=hizzle-logger&hlog_source=noptin-connection-' . urlencode( $this->slug ) ) ),
					"noptin_{$slug}_debug_mode",
					__( 'View logs', 'newsletter-optin-box' )
				),
				'default'     => false,
				'restrict'    => $this->get_enable_integration_option_name(),
			);
		}

		if ( ! $this->is_oauth ) {
			$options = $this->add_connection_options( $options );
		}

		if ( $this->is_connected() ) {

			// Double optin.
			if ( $this->double_optin ) {

				$options[ "noptin_{$slug}_enable_double_optin" ] = array(
					'type'        => 'checkbox_alt',
					'el'          => 'input',
					'section'	  => 'integrations',
					'label'       => __( 'Enable double opt-in', 'newsletter-optin-box' ),
					'description' => __( 'Send contacts an opt-in confirmation email when they sign up', 'newsletter-optin-box' ),
					'restrict'    => $this->get_enable_integration_option_name(),
				);

			}

			// Default lists.
			foreach ( $this->list_types as $list_type ) {

				if ( $list_type->is_taggy ) {

					$options[ "noptin_{$slug}_default_{$list_type->id}" ] = array(
						'el'          => 'input',
						'section'     => 'integrations',
						'label'       => sprintf(
							// translators: %s is the list type name.
							__( 'Default %s', 'newsletter-optin-box' ),
							$list_type->name_plural
						),
						'restrict'    => $this->get_enable_integration_option_name(),
						'placeholder' => 'Example 1, Example 2',
					);

				} elseif ( empty( $list_type->parent_id ) ) {

					$options[ "noptin_{$slug}_default_{$list_type->id}" ] = array(
						'el'          => 'select',
						'section'     => 'integrations',
						'options'     => $list_type->get_lists(),
						'placeholder' => __( 'Select an option', 'newsletter-optin-box' ),
						'label'       => sprintf(
							// translators: %s is the list type name.
							__( 'Default %s', 'newsletter-optin-box' ),
							$list_type->name
						),
						'restrict'    => $this->get_enable_integration_option_name(),
					);

				}
			}

			// Extra integration options.
			$options = $this->get_options( $options );

		}

		$options = apply_filters( 'noptin_single_integration_settings', $options, $slug, $this );

		if ( $this->is_oauth ) {
			$options = $this->add_connection_options( $options );
		}

		// Register the options.
		$_options[ "settings_section_$slug" ] = array(
			'id'          => "settings_section_$slug",
			'el'          => 'settings_section',
			'children'    => $options,
			'section'     => 'integrations',
			'heading'     => sanitize_text_field( $this->name ),
			'description' => sanitize_text_field( $this->description ),
			'badge'       => $this->get_hero_extra(),
		);

		return apply_filters( "noptin_{$slug}_integration_settings", $_options, $this );

	}

	/**
	 * Adds connection options to settings fields.
	 *
	 * @return false
	 */
	public function add_connection_options( $options ) {

		if ( ! $this->is_oauth ) {
			return $options;
		}

		// Connected ... show connection button.
		if ( $this->is_connected() ) {

			$options[ "{$this->slug}_disconnect" ] = array(
				'class'    => 'oauth-disconnect',
				'el'       => 'button',
				'section'  => 'integrations',
				'label'    => sprintf(
					// translators: %s is the remote name
					__( 'Disconnect from %s', 'newsletter-optin-box' ),
					$this->name
				),
				'url'      => $this->get_oauth_url( true ),
				'restrict' => $this->get_enable_integration_option_name(),
			);

		} else {

			$options[ "{$this->slug}_connect" ] = array(
				'class'    => 'oauth-connect',
				'el'       => 'button',
				'section'  => 'integrations',
				'label'    => sprintf(
					// translators: %s is the remote name
					__( 'Connect to %s', 'newsletter-optin-box' ),
					$this->name
				),
				'url'      => $this->get_oauth_url(),
				'restrict' => $this->get_enable_integration_option_name(),
			);

		}

		return $options;
    }

	/**
	 * Extra setting fields.
	 *
	 * @return false
	 */
	public function get_options( $options ) {
		return $options;
	}

	/**
	 * Returns the settings URL.
	 *
	 * @return string
	 */
	public function get_settings_url() {

		return add_query_arg(
			array(
				'page'        => 'noptin-settings',
				'tab'         => 'integrations',
				'integration' => $this->slug,
			),
			admin_url( 'admin.php#noptin-settings-section-settings_section_' . $this->slug )
		);
	}

	/**
	 * Returns the OAuth URL.
	 *
	 * @param bool $disconnect
	 * @return string
	 */
	public function get_oauth_url( $disconnect = false ) {

		if ( $disconnect ) {
			return add_query_arg(
				array(
					'noptin_admin_action' => 'noptin_disconnect_' . $this->slug,
					'noptin_nonce'        => wp_create_nonce( 'noptin_oauth_disconnect' ),
				),
				$this->get_settings_url()
			);
		}

		$url = add_query_arg(
			array(
				'noptin_admin_action' => 'noptin_connect_' . $this->slug,
				'noptin_nonce'        => wp_create_nonce( 'noptin_oauth_connect' ),
			),
			$this->get_settings_url()
		);

		return add_query_arg( 'redirect_url', rawurlencode( $url ), 'http://noptin.com/oauth/' . $this->slug );
	}

	/**
	 * Handles oauth connections.
	 */
	public function oauth_connect() {

		if ( empty( $_GET['noptin_nonce'] ) || ! wp_verify_nonce( $_GET['noptin_nonce'], 'noptin_oauth_connect' ) ) {
			return;
		}

		$error = '';

		if ( empty( $_GET['access_token'] ) ) {
			$error = __( 'Error: Access token not provided.', 'newsletter-optin-box' );
		}

		if ( ! empty( $_GET['error'] ) ) {
			$error = esc_html( $_GET['error'] );
		}

		if ( ! empty( $_GET['error_description'] ) ) {
			$error = esc_html( $_GET['error_description'] );
		}

		// If there was an error, abort.
		if ( ! empty( $error ) ) {
			noptin()->admin->show_error( $error );
			wp_safe_redirect( $this->get_settings_url() );
			exit;
		}

		$this->save_oauth_settings( urldecode_deep( $_GET ) );
		wp_safe_redirect( $this->get_settings_url() );
		exit;

	}

	/**
	 * Saves the connection settings.
	 *
	 * @param array $data
	 */
	public function save_oauth_settings( $data ) {
		if ( isset( $data['access_token'] ) ) {
			update_noptin_option( $this->slug . '_access_token', $data['access_token'] );
		}
	}

	/**
	 * Handles oauth disconnections.
	 */
	public function oauth_disconnect() {

		if ( empty( $_GET['noptin_nonce'] ) || ! wp_verify_nonce( $_GET['noptin_nonce'], 'noptin_oauth_disconnect' ) ) {
			return;
		}

		// Delete settings.
		$this->delete_oauth_settings();

		// Empty cache.
		$this->empty_cache();

		// Redirect back to the settings page.
		wp_safe_redirect( $this->get_settings_url() );
		exit;

	}

	/**
	 * Deletes the connection settings.
	 *
	 */
	public function delete_oauth_settings() {
		update_noptin_option( $this->slug . '_access_token', '' );
	}

	/**
	 * Empties the cache.
	 */
	public function empty_cache() {

		// Abort if we have no lists.
		if ( empty( $this->list_types ) ) {
			return;
		}

		// Some cache keys are prefixed with the parent list ID.
		$default_type    = $this->get_default_list_type();
		$parent_list_ids = empty( $default_type ) ? array() : array_keys( $default_type->get_lists() );

		foreach ( $this->list_types as $list_type ) {
			$list_type->empty_cache();

			foreach ( $parent_list_ids as $list_id ) {
				delete_transient( $list_type->get_cache_key( $list_id ) );
			}
		}
	}

	/**
	 * Adds a new Noptin subscriber to the remote connection.
	 *
	 * @param id $subscriber_id The noptin subscriber ID.
	 * @param array $data The noptin subscriber data.
	 * @since 1.0.0
	 */
	public function add_contact( $subscriber_id, $data = array() ) {

		// Retrieve the Noptin subscriber.
		$noptin_subscriber = new \Noptin_Subscriber( $subscriber_id );
		if ( ! $noptin_subscriber->exists() ) {
			return;
		}

		// Sanitized the provided data.
		$data = $this->prepare_new_subscriber_data( $noptin_subscriber, $data );

		// Retrieve subscriber args.
		$args = $data[ $this->slug ];

		// Abort if no list was selected.
		$selected_list = $args[ $this->get_default_list_type()->id ];

		if ( empty( $selected_list ) || '-1' === $selected_list ) {
			return;
		}

		// Custom fields.
		if ( empty( $args['custom_fields'] ) ) {
			$args['custom_fields'] = array();
		}

		$args['custom_fields'] = array_replace(
			$args['custom_fields'],
			$this->prepare_list_fields( $noptin_subscriber, $selected_list )
		);

		// Process the subscriber.
		$this->process_contact( $noptin_subscriber->email, $args );
	}

	/**
	 * Removes a remote subscriber by email.
	 *
	 * @param string $email_address The email ID.
	 * @since 1.0.0
	 */
	public function remove_contact( $email_address ) {
		// Optionally implemented by child classes.
	}

	/**
	 * Displays form options.
	 *
	 * @param \Noptin_Form $form
	 */
	public function display_form_options( $form ) {
		$integration = $this;
		include plugin_dir_path( __FILE__ ) . 'views/form-options.php';
	}

	/**
	 * Adds custom state to the legacy form builder.
	 *
	 * @param array $defaults
	 * @return array
	 */
	public function add_custom_state_to_legacy_forms( $defaults ) {

		foreach ( $this->list_types as $list_type ) {
			$defaults[ "{$this->slug}_{$list_type->id}" ] = $list_type->get_default_list_id();
		}

		return $defaults;
	}

	/**
	 * Adds custom settings to legacy forms builder.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function add_custom_fields_to_legacy_forms_editor( $settings ) {

		// Abort early if we're not connected.
		if ( ! $this->is_connected() ) {

			// Connection error.
			$error = $this->last_error;

			if ( empty( $error ) ) {
				$error = sprintf(
					// translators: %s is the name of the integration.
					__( 'You are not connected to %s', 'newsletter-optin-box' ),
					$this->name
				);
			}

			$settings['integrations'][ $this->slug ] = array(
				'el'       => 'panel',
				'title'    => $this->name,
				'id'       => $this->slug,
				'open'     => true,
				'children' => array(
					"{$this->slug}_text" => array(
						'el'      => 'paragraph',
						'content' => "Error: $error",
						'style'   => 'color:#F44336;',
					),
				),
			);

			return $settings;
		}

		$custom = array();

		// Default lists.
		foreach ( $this->list_types as $list_type ) {

			$option = "{$this->slug}_{$list_type->id}";

			if ( $list_type->is_taggy ) {

				$custom[ $option ] = array(
					'el'          => 'input',
					'label'       => sprintf(
						'%1$s %2$s',
						$this->name,
						$list_type->name_plural
					),
					'placeholder' => 'Example 1, Example 2',
					'tooltip'     => sprintf(
						// translators: %s is the list type, %s is the contact type.
						__( 'Comma separated list of %1$s to add to %2$s.', 'newsletter-optin-box' ),
						strtolower( $list_type->name_plural ),
						strtolower( $this->subscriber_name_plural )
					),
				);

			} elseif ( empty( $list_type->parent_id ) ) {

				$custom[ $option ] = array(
					'el'          => 'select',
					'options'     => array_replace(
						array( '-1' => __( 'None', 'newsletter-optin-box' ) ),
						$list_type->get_lists()
					),
					'placeholder' => __( 'Select an option', 'newsletter-optin-box' ),
					'label'       => sprintf(
						'%1$s %2$s',
						$this->name,
						$list_type->name
					),
					'tooltip'     => sprintf(
						// translators: %s is the list type, %s is the contact type.
						__( 'Select the %1$s to add %2$s to.', 'newsletter-optin-box' ),
						strtolower( $list_type->name ),
						strtolower( $this->subscriber_name_plural )
					),
				);

			}
		}

		$settings['integrations'][ $this->slug ] = array(
			'el'       => 'panel',
			'title'    => $this->name,
			'id'       => $this->slug,
			'open'     => true,
			'children' => $custom,
		);

		return $settings;
	}

	/**
	 * Registers list options.
	 *
	 * @since 1.0.0
	 * @param array $options
	 * @param string $slug
	 * @param \Noptin_Abstract_Integration $integration
	 */
	public function add_list_options( $options, $slug, $integration ) {

		if ( 'normal' === $integration->integration_type || 'ecommerce' === $integration->integration_type ) {

			$via  = str_replace( '_form', '', $slug );
			$via .= 'ecommerce' === $integration->integration_type ? '_checkout' : '';

			// Default lists.
			foreach ( $this->list_types as $list_type ) {

				$option = sanitize_text_field( "noptin_{$this->slug}_{$via}_default_{$list_type->id}" );

				if ( $list_type->is_taggy ) {

					$options[ $option ] = array(
						'el'          => 'input',
						'section'     => 'integrations',
						'label'       => sprintf(
							'%1$s %2$s',
							$this->name,
							$list_type->name_plural
						),
						'restrict'    => sprintf(
							'%s && %s',
							$this->get_enable_integration_option_name(),
							$integration->get_enable_integration_option_name()
						),
						'placeholder' => 'Example 1, Example 2',
						'description' => sprintf(
							// translators: %s is the list type, %s is the contact type.
							__( 'Comma separated list of %1$s to add to %2$s.', 'newsletter-optin-box' ),
							strtolower( $list_type->name_plural ),
							strtolower( $this->subscriber_name_plural )
						),
					);

				} elseif ( empty( $list_type->parent_id ) ) {

					$options[ $option ] = array(
						'el'          => 'select',
						'section'     => 'integrations',
						'options'     => array_replace(
							array( '-1' => __( 'None', 'newsletter-optin-box' ) ),
							$list_type->get_lists()
						),
						'placeholder' => __( 'Select an option', 'newsletter-optin-box' ),
						'label'       => sprintf(
							'%1$s %2$s',
							$this->name,
							$list_type->name
						),
						'restrict'    => sprintf(
							'%s && %s',
							$this->get_enable_integration_option_name(),
							$integration->get_enable_integration_option_name()
						),
						'description' => sprintf(
							// translators: %s is the list type, %s is the contact type.
							__( 'Select the %1$s to add %2$s to.', 'newsletter-optin-box' ),
							strtolower( $list_type->name ),
							strtolower( $this->subscriber_name_plural )
						),
					);

				}
			}
		}

		return $options;
	}

	/**
	 * Returns an array of subscriber fields.
	 *
	 * @param \Noptin_Subscriber $subscriber
	 * @param array $data
	 * @since 1.0.0
	 * @return array
	 */
	public function prepare_new_subscriber_data( $subscriber, $data ) {
		// This is usually saved with the new forms.
		delete_noptin_subscriber_meta( $subscriber->id, $this->slug );

		// Format the data.
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( empty( $data[ $this->slug ] ) ) {
			$data[ $this->slug ] = array();
		}

		// Maybe set a default list.
		$default_type    = $this->get_default_list_type();
		$is_default_list = false;

		if ( ! isset( $data[ $this->slug ][ $default_type->id ] ) ) {
			$data[ $this->slug ][ $default_type->id ] = $default_type->get_default_list_id();
			$is_default_list = true;
		}

		// Fetch lists, tags, etc from the sign-up method.
		$form = $subscriber->get( '_subscriber_via' );

		if ( empty( $form ) ) {
			return $data;
		}

		if ( ! is_numeric( $form ) ) {
			$option = sanitize_text_field( "noptin_{$this->slug}_{$form}_default_" );

			// Loop through all list types.
			foreach ( $this->list_types as $list_type ) {

				$value = get_noptin_option( sanitize_text_field( $option . $list_type->id ) );

				if ( null === $value ) {
					$value = $list_type->get_default_list_id();
				}

				if ( empty( $value ) || '-1' === $value ) {
					continue;
				}

				// Abort if this is already set.
				if ( isset( $data[ $this->slug ][ $list_type->id ] ) && ( $list_type->id !== $default_type->id || ! $is_default_list ) ) {
					continue;
				}

				if ( $list_type->is_taggy ) {
					$data[ $this->slug ][ $list_type->id ] = noptin_parse_list( $value, true );
				} else {
					$data[ $this->slug ][ $list_type->id ] = $value;
				}
			}

			return $data;
		}

		// New type forms are preprocessed.
		if ( ! is_legacy_noptin_form( absint( $form ) ) ) {
			return $data;
		}

		// Legacy forms.
		$form = absint( $form );
		$form = noptin_get_optin_form( $form );

		// Ensure the form exists.
		if ( ! $form->is_published() ) {
			return $data;
		}

		// Loop through all list types.
		foreach ( $this->list_types as $list_type ) {
			$value = $form->__get( "{$this->slug}_{$list_type->id}" );

			if ( empty( $value ) ) {
				continue;
			}

			if ( $list_type->is_taggy ) {
				$data[ $this->slug ][ $list_type->id ] = noptin_parse_list( $value, true );
			} else {
				$data[ $this->slug ][ $list_type->id ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Processes submitted connection data.
	 */
	public function add_connection_details_to_submitted( $data, $submitted ) {

		if ( empty( $data[ $this->slug ] ) ) {
			$data[ $this->slug ] = array();
		}

		foreach ( $submitted as $key => $value ) {

			// Check if key begins with the integration slug.
			if ( 0 !== strpos( $key, $this->slug ) ) {
				continue;
			}

			// Remove the integration slug.
			$key = str_replace( $this->slug . '_', '', $key );

			$data[ $this->slug ][ $key ] = $value;
		}

		return $data;
	}

	/**
	 * Maps customer fields to subscriber fields.
	 *
	 * @since 1.0.0
	 */
	public function map_contact_to_custom_fields() {

		// Check if this integration uses universal custom fields.
		if ( $this->has_universal_fields ) {
			return $this->map_custom_fields_settings( $this->get_custom_fields( false ), "field.{$this->slug}" );
		}

		// Loop through all available lists.
		foreach ( $this->get_default_list_type()->get_lists() as $list_id => $list_name ) {
			$this->map_custom_fields_settings(
				$this->get_custom_fields( $list_id ),
				"field.{$this->slug}_{$list_id}",
				$list_name
			);
		}

	}

	/**
	 * Maps customer fields to subscriber fields.
	 *
	 * @param Custom_Field[] $custom_fields The custom fields.
	 * @param string         $option_id The option id.
	 * @param string         $list_name The list name.
	 * @since 1.0.0
	 */
	private function map_custom_fields_settings( $custom_fields, $option_id, $list_name = false ) {

		if ( ! empty( $custom_fields ) ) {

			\Noptin_Vue::render_el(
				$option_id,
				array(
					'el'       => 'select',
					'label'    => $list_name ? sprintf(
						// translators: %s is the integration name.
						__( '%1$s Equivalent (%2$s)', 'newsletter-optin-box' ),
						esc_html( $this->name ),
						esc_html( $list_name )
					) : sprintf(
						// translators: %s is the integration name.
						__( '%s Equivalent', 'newsletter-optin-box' ),
						esc_html( $this->name )
					),
					'restrict' => "field.merge_tag != 'email' && " . $this->get_enable_integration_option_name(),
					'options'  => array_replace(
						array(
							'' => __( 'Not Mapped', 'newsletter-optin-box' ),
						),
						wp_list_pluck( $custom_fields, 'name', 'id' )
					),
					'normal'   => false,
					'default'  => '',
				)
			);
		}

	}

	/**
	 * Fetch list fields.
	 *
	 * @param Noptin_Subscriber $noptin_subscriber
	 * @param string $list_id
	 * @since 1.0.0
	 * @return array
	 */
	public function prepare_list_fields( $noptin_subscriber, $list_id ) {

		$fields = array();
		$key    = $this->has_universal_fields ? $this->slug : "{$this->slug}_{$list_id}";

		foreach ( get_noptin_custom_fields() as $field ) {

			if ( empty( $field[ $key ] ) ) {
				continue;
			}

			$value = $noptin_subscriber->get( $field['merge_tag'] );

			if ( '' !== $value ) {
				$fields[ $field[ $key ] ] = $value;
			}
		}

		return $fields;

	}

	/**
	 * Add / Update a contact.
	 *
	 * @param string $email
	 * @param array $args
	 * @return bool
	 */
	abstract public function process_contact( $email, $args );

	/**
	 * Retrieves an array of custom fields.
	 *
	 * @param string $list_id The list ID to retrieve fields for.
	 * @return Custom_Field[]
	 */
	abstract public function get_custom_fields( $list_id );

	/**
	 * Fetches the default list type.
	 *
	 * @return List_Type
	 */
	abstract public function get_default_list_type();

	/**
	 * Returns extra texts to append to the hero
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_hero_extra() {

		$option   = $this->get_enable_integration_option_name();
		$disabled = __( 'Disabled', 'newsletter-optin-box' );

		if ( $this->is_connected() ) {
			$enabled = __( 'Connected', 'newsletter-optin-box' );
		} else {

			if ( ! empty( $this->last_error ) ) {
				$enabled = __( 'Not Connected', 'newsletter-optin-box' );
				$enabled = "$enabled <em>( {$this->last_error} )</em>";
			} else {
				$enabled = __( 'Enabled', 'newsletter-optin-box' );
			}
		}

		return sprintf(
			'<span style="color: #43a047;" v-if="%s">%s</span><span style="color: #616161;" v-else>%s</span>',
			esc_attr( $option ),
			wp_kses_post( $enabled ),
			wp_kses_post( $disabled )
		);
	}

	/**
	 * Checks if double opt-in is enabled.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function enabled_double_optin() {
		return (bool) get_noptin_option( "noptin_{$this->slug}_enable_double_optin", false );
	}

	/**
	 * Checks if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_mode() {
		return (bool) get_noptin_option( "noptin_{$this->slug}_debug_mode", false );
	}

	/**
	 * Logs debug messages.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 * @param array  $data  Optional. Extra error data. Default empty array.
	 */
	public function log( $message, $level = 'info', $data = array() ) {

		if ( $this->is_debug_mode() ) {

			if ( is_array( $level ) ) {
				$data  = $level;
				$level = 'info';
			}

			$context = array(
				'connection' => $this->name,
				'module'     => 'noptin-connection',
				'source'     => 'noptin-connection-' . $this->slug,
				'data'       => $data,
			);

			\Hizzle\Logger\Logger::get_instance()->log( $level, $message, $context );
		}
	}

}

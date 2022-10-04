<?php

namespace Noptin\Connection;

/**
 * Handles sending campaigns via the remote connections.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class represents a single list type.
 */
abstract class Email_Sender {

    /**
	 * Initiates new non-blocking asynchronous request.
	 *
	 */
	public function __construct() {

		// Register sender.
		add_filter( 'noptin_email_senders', array( $this, 'register_sender' ) );

        // Displays sender options.
		add_action( 'noptin_sender_options_' . $this->sender, array( $this, 'display_sending_options' ) );

		// Adds a new email to the queue.
		add_action( 'noptin_send_email_via_' . $this->sender, array( $this, 'send' ) );

	}

    /**
	 * Registers our sender.
	 *
	 */
	abstract public function register_sender( $senders );

	/**
	 * Generate email content.
	 *
	 * @param \Noptin_Newsletter_Email|\Noptin_automated_Email $campaign
	 * @return string.
	 */
	public function generate_email_content( $campaign ) {

		return noptin_generate_email_content(
			$campaign,
			array(),
			false
		);
	}

	/**
	 * Retrieves the connection.
	 *
	 * @return Connection
	 */
	public function get_connection() {
		return noptin()->integrations->integrations[ $this->sender ];
	}

    /**
	 * Displays newsletter sending options.
	 *
	 * @param \Noptin_Newsletter_Email|\Noptin_automated_Email $campaign
	 *
	 * @return bool
	 */
	public function display_sending_options( $campaign ) {

        $connection = $this->get_connection();

        $default_list_type = $connection->get_default_list_type();

		// Prepare sender options.
        $lists           = array();
		$current_options = $campaign->get( $this->slug );

		if ( empty( $current_options ) ) {
			$current_options = array();
		}

        foreach ( $connection->list_types as $list_type ) {

            // Abort if it can't be used for sending.
            if ( ! $list_type->can_filter_campaigns ) {
                continue;
            }

            // If this is the default list type, add it.
            if ( $default_list_type->id === $list_type->id ) {
                $lists[ $list_type->id ] = array(
					'name'        => $list_type->name,
                    'value'       => isset( $current_options[ $list_type->id ] ) ? $current_options[ $list_type->id ] : $list_type->get_default_list_id(),
                    'options'     => $list_type->get_lists(),
					'description' => sprintf(
						// translators: %s is the name of the list type.
						__( 'Select the %1$s to send this campaign to.', 'newsletter-optin-box' ),
						strtolower( $list_type->name )
					),
				);

				continue;
			}

            // If has a parent that is not the default list type, abort.
            if ( ! empty( $list_type->parent_id ) && $default_list_type->id !== $list_type->parent_id ) {
                continue;
            }

			// Taggy children.
			if ( ! empty( $list_type->is_taggy ) ) {
				$lists[ $list_type->id ] = array(
					'value'       => isset( $current_options[ $list_type->id ] ) ? $current_options[ $list_type->id ] : '',
					'taggy'       => true,
					'name'        => $list_type->name_plural,
					'description' => sprintf(
						// translators: %s is the name of the list type.
						__( 'Optionally, only send to %1$s with the specified %2$s.', 'newsletter-optin-box' ),
						strtolower( $connection->subscriber_name_plural ),
						strtolower( $list_type->name_plural )
					),
				);
				continue;
			}

			// Orphans.
			if ( empty( $list_type->parent_id ) ) {
				$lists[ $list_type->id ] = array(
					'value'       => isset( $current_options[ $list_type->id ] ) ? noptin_parse_list( $current_options[ $list_type->id ] ) : array( $list_type->get_default_list_id() ),
					'options'     => $list_type->get_lists(),
					'multiple'    => true,
					'name'        => $list_type->name_plural,
					'description' => sprintf(
						// translators: %s is the name of the list type.
						__( 'Optionally, only send to %1$s in the specified %2$s.', 'newsletter-optin-box' ),
						strtolower( $connection->subscriber_name_plural ),
						strtolower( $list_type->name_plural )
					),
				);

				return;
			}

            // Normal children.
			foreach ( array_keys( $default_list_type->get_lists() ) as $list_id ) {

				$lists[ $list_type->id . '_' . $list_id ] = array(
					'value'       => isset( $current_options[ $list_type->id ] ) ? array_filter( noptin_parse_list( $current_options[ $list_type->id ] ) ) : array( $list_type->get_default_list_id() ),
					'options'     => $list_type->get_lists( $list_id ),
					'multiple'    => true,
					'name'        => $list_type->name_plural,
					'description' => sprintf(
						// translators: %s is the name of the list type.
						__( 'Optionally, only send to %1$s in the specified %2$s.', 'newsletter-optin-box' ),
						strtolower( $connection->subscriber_name_plural ),
						strtolower( $list_type->name_plural )
					),
					'if_parent'   => $list_type->parent_id,
				);
			}
        }

		?>

		<?php foreach ( $lists as $list_type => $details ) : ?>
			<div class="noptin-margin-y noptin-<?php echo esc_attr( $this->sender ); ?>-campaign-sender-field-wrapper" data-parent="<?php echo esc_attr( empty( $details['if_parent'] ) ? 'none' : $details['if_parent'] ); ?>">

				<label for="noptin-<?php echo esc_attr( $this->sender ); ?>-campaign-sender-field-<?php echo esc_attr( $list_type ); ?>">
					<?php echo esc_html( $details['name'] ); ?>
				</label>

				<?php if ( ! empty( $details['taggy'] ) ) : ?>

					<input
						type="text"
						id="noptin-<?php echo esc_attr( $this->sender ); ?>-campaign-sender-field-<?php echo esc_attr( $list_type ); ?>"
						class="noptin-col"
						name="noptin_email[<?php echo esc_attr( $this->slug ); ?>][<?php echo esc_attr( $list_type ); ?>]"
						value="<?php echo esc_attr( implode( ', ', noptin_parse_list( $details['value'] ) ) ); ?>"
						placeholder="Example 1, Example 2" />

				<?php elseif ( ! empty( $details['multiple'] ) ) : ?>

					<ul style="overflow: auto; min-height: 42px; max-height: 200px; padding: 0 .9em; border: solid 1px #dfdfdf; background-color: #fdfdfd;">

						<?php foreach ( $details['options'] as $option_key => $option_value ) : ?>
							<li>
								<label>
									<input
										name='noptin_email[<?php echo esc_attr( $this->slug ); ?>][<?php echo esc_attr( $list_type ); ?>][]'
										id="noptin-<?php echo esc_attr( $this->sender ); ?>-campaign-sender-field-<?php echo esc_attr( $list_type ); ?>__<?php echo esc_attr( $option_key ); ?>"
										type='checkbox'
										value='<?php echo esc_attr( $option_key ); ?>'
										<?php checked( in_array( $option_key, $details['value'], true ) ); ?>
									>
									<span><?php echo esc_html( $option_value ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>

					<input type="hidden" name="noptin_email[<?php echo esc_attr( $this->slug ); ?>][<?php echo esc_attr( $list_type ); ?>][]" value="0" />
				<?php else : ?>

					<select
						id="noptin-<?php echo esc_attr( $this->sender ); ?>-campaign-sender-field-<?php echo esc_attr( $list_type ); ?>"
						class="noptin-col"
						name="noptin_email[<?php echo esc_attr( $this->slug ); ?>][<?php echo esc_attr( $list_type ); ?>]"
					>
						<?php foreach ( $details['options'] as $option_key => $option_value ) : ?>
							<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, $details['value'] ); ?>>
								<?php echo esc_html( $option_value ); ?>
							</option>
						<?php endforeach; ?>
					</select>

				<?php endif; ?>

				<?php if ( ! empty( $details['description'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $details['description'] ); ?></p>
				<?php endif; ?>
            </div>
		<?php endforeach; ?>

		<?php

	}

	/**
	 * Sends newsletter campaign to remote contacts.
	 *
	 * @param \Noptin_Newsletter_Email $campaign
	 *
	 * @return void
	 */
	public function send( $campaign ) {

		// Bail if the campaign is not active.
		if ( ! $campaign->can_send() ) {
			return;
		}

		// Prepare sender options.
        $lists           = array();
		$current_options = $campaign->get( $this->slug );

		if ( empty( $current_options ) ) {
			return;
		}

		/** @var Connection $connection */
        $connection = noptin()->integrations->integrations[ $this->sender ];

        $default_list_type = $connection->get_default_list_type();

		// Abort if the default list type is not set.
		if ( empty( $current_options[ $default_list_type->id ] ) || '-1' === $current_options[ $default_list_type->id ] ) {
			return;
		}

		$sends_to = $current_options[ $default_list_type->id ];

		foreach ( $connection->list_types as $list_type ) {

			// If this is the default list type, add it.
            if ( $default_list_type->id === $list_type->id ) {
				$lists[ $list_type->id ] = $sends_to;
				continue;
			}

            // Abort if it can't be used for sending.
            if ( ! $list_type->can_filter_campaigns ) {
                continue;
            }

			// Process taggy lists && orphans.
			if ( ! empty( $list_type->is_taggy ) && empty( $list_type->parent_id ) ) {
				$value = isset( $current_options[ $list_type->id ] ) ? array_filter( noptin_parse_list( $current_options[ $list_type->id ] ) ) : '';
			} else {
				$value = isset( $current_options[ $list_type->id . '_' . $sends_to ] ) ? array_filter( noptin_parse_list( $current_options[ $list_type->id . '_' . $sends_to ] ) ) : '';
			}

			// Add the list.
			if ( ! empty( $value ) ) {
				$lists[ $list_type->id ] = $value;
			}
        }

		// Send the campaign.
		$this->send_campaign( $campaign, $lists );

		// Update status.
		update_post_meta( $campaign->id, 'completed', 1 );

	}

	/**
	 * Handles the actual sending of the campaign.
	 *
	 * @param \Noptin_Newsletter_Email $campaign The campaign to send.
	 * @param array                    $lists The lists to send to.
	 */
	abstract public function send_campaign( $campaign, $lists );
}

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
abstract class Email_Sender  extends \Noptin_Mass_Mailer {

    /**
	 * Initiates new non-blocking asynchronous request.
	 *
     * @param string $connection_slug
	 */
	public function __construct( $connection_slug ) {
        $this->sender = $connection_slug;

        // Initiate the parent class.
        parent::__construct();

        add_filter( 'noptin_email_senders', array( $this, 'register_sender' ) );
	}

    /**
	 * Registers our sender.
	 *
	 */
	abstract public function register_sender( $senders );

    /**
	 * Displays newsletter sending options.
	 *
	 * @param \Noptin_Newsletter_Email|\Noptin_automated_Email $campaign
	 *
	 * @return bool
	 */
	public function display_sending_options( $campaign ) {

        /** @var Connection $connection */
        $connection = noptin()->integrations->integrations[ $this->sender ];

        $default_list_type = $connection->get_default_list_type();

		// Prepare sender options.
        $options = array();

        foreach ( $connection->list_types as $list_type ) {

            // Abort if it can't be used for sending.
            if ( ! $list_type->can_filter_campaigns ) {
                continue;
            }

            // If this is the default list type, add it.
            if ( $default_list_type->id === $list_type->id ) {
                $options[ $list_type->id ] = array(
                    'value'   => $campaign->get( $this->sender . '_' . $list_type->id ),
                    'options' = $list_type->get_lists(),
                );
                continue;
            }

            // If has a parent that is not the default list type, abort.
            if ( ! empty( $list_type->parent_id ) && $default_list_type->id !== $list_type->parent_id ) {
                continue;
            }

            // Add the children.
            $options[ $list_type->id ] = array(
                'value'   => noptin_parse_list( $campaign->get( $this->sender . '_' . $list_type->id ), true ),
                'options' = $list_type->is_taggy ? null : $list_type->get_lists(),
            );
        }
		$options = $campaign->get( 'wp_users_options' );
		$roles   = empty( $options['roles'] ) ? array() : $options['roles'];
		$exclude = empty( $options['exclude'] ) ? array() : wp_parse_id_list( $options['exclude'] );

        can_filter_campaigns
		?>

            <?php foreach ( $connection->list_types as $list_type ) : ?>
                <?php if ( $list_type->can_filter_campaigns ) : ?>
                    <p>
                        <strong><?php printf( esc_html__( 'Filter recipients by %s' )) ?></strong>
                        <div class="noptin-campaign-list-type">
                            <label>
                                <input type="radio" name="wp_users_options[list_type]" value="<?php echo esc_attr( $list_type ); ?>" <?php checked( $list_type, $default_list_type ); ?> />
                                <?php echo esc_html( $list_type_label ); ?>
                            </label>
                        </div>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>

			<p>
				<strong><?php _e( 'Select the user roles to send this email to', 'noptin-addons-pack' ); ?></strong>
				<ul style="overflow: auto; min-height: 42px; max-height: 200px; padding: 0 .9em; border: solid 1px #dfdfdf; background-color: #fdfdfd;">
					<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
						<li>
							<label>
								<input
									name='noptin_email[wp_users_options][roles][]'
									type='checkbox'
									value='<?php echo esc_attr( $role ); ?>'
									<?php checked( in_array( $role, $roles ) ); ?>
								>
								<span><?php echo esc_html( translate_user_role( $name ) ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</p>

			<p>
				<label class="noptin-margin-y">
					<strong style="display: block;"><?php _e( 'Exclude', 'noptin-addons-pack' ); ?></strong>
					<input type="text" value="<?php echo implode( ', ', wp_parse_id_list( $exclude ) ); ?>" class="noptin-col" name="noptin_email[wp_users_options][exclude]" />
					<p class="description"><?php _e( 'Enter a comma separated list of user ids who should not receive this email', 'noptin-addons-pack' ); ?></p>
				</label>
			</p>

		<?php

	}

	/**
	 * Sends a single email to a user.
	 *
	 * @param Noptin_Newsletter_Email $campaign
	 * @param int $recipient
	 *
	 * @return bool
	 */
	public function _send( $campaign, $recipient ) {

		$user = get_user_by( 'ID', $recipient );
		$key  = '_campaign_' . $campaign->id;

		// Bail if the user is not found or is unsubscribed...
		if ( ! $user || noptin_is_wp_user_unsubscribed( $user->ID ) ) {
			return null;
		}

		// ... or was already sent the email.
		if ( '' !== get_user_meta( $user->ID, $key, true ) ) {
			return null;
		}

		// Generate and send the actual email.
		noptin()->emails->newsletter->user = $user;
		$result = noptin()->emails->newsletter->send( $campaign, $campaign->id, $user->user_email );

		// Log the send.
		update_user_meta( $user->ID, $key, (int) $result );

		return $result;
	}

	/**
	 * Fired after a campaign is done sending.
	 *
	 * @param @param Noptin_Newsletter_Email $campaign
	 *
	 */
	public function done_sending( $campaign ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->usermeta,
			array(
				'meta_key' => '_campaign_' . $campaign->id,
			)
		);

	}

	/**
	 * Fetches relevant users for the campaign.
	 *
	 * @param Noptin_Newsletter_Email $campaign
	 */
	public function _fetch_recipients( $campaign ) {

		$options = $campaign->get( 'wp_users_options' );
		$roles   = empty( $options['roles'] ) ? array() : $options['roles'];
		$exclude = empty( $options['exclude'] ) ? array() : wp_parse_id_list( $options['exclude'] );

		// TODO: Add more filters.
		return get_users(
			apply_filters(
				'noptin_mass_mailer_user_query',
				array(
					'fields'   => 'ID',
					'role__in' => $roles,
					'orderby'  => 'ID',
					'exclude'  => $exclude,
				),
				$campaign
			)
		);

	}

}

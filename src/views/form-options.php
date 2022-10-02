<?php
/**
 * Displays in the integrations tab in the form editor.
 *
 * @var Noptin_Form $form
 * @var Noptin\Connection\Connection $integration
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$all_settings = $form->settings;

?>

<fieldset id="noptin-form-integrations-panel-<?php echo esc_attr( $integration->slug ); ?>" class="noptin-settings-panel">
	<button
		aria-expanded="true"
		aria-controls="noptin-form-integrations-panel-<?php echo esc_attr( $integration->slug ); ?>-content"
		type="button"
		class="noptin-accordion-trigger"
	>
        <span class="title"><?php echo esc_html( $integration->name ); ?></span>
		<span class="icon"></span>
	</button>

	<div class="noptin-settings-panel__content" id="noptin-form-integrations-panel-<?php echo esc_attr( $integration->slug ); ?>-content">

		<?php if ( ! $integration->is_connected() ) : ?>
			<p style="color:#F44336;">
				<?php
					printf(
						'Error: %s',
						! empty( $integration->last_error ) ? esc_html( $integration->last_error ) : sprintf( /* translators: %s integration name */ esc_html__( 'You are not connected to %s', 'newsletter-optin-box' ), esc_html( $integration->name ) )
					);
				?>
			</p>
		<?php else : ?>

            <table class="form-table noptin-form-settings noptin-integration-settings">

                <?php foreach ( $integration->list_types as $list_type ) : ?>

                    <?php if ( $list_type->is_taggy || empty( $list_type->parent_id ) ) : ?>

                        <tr valign="top" class="form-field-row form-field-row-list">
                            <th scope="row">
                                <label for="noptin-form-<?php echo esc_attr( $integration->slug ); ?>-<?php echo esc_attr( $list_type->id ); ?>">
                                    <?php echo esc_html( $list_type->is_taggy ? $list_type->name_plural : $list_type->name ); ?>
                                </label>
                            </th>
					        <td>
								<?php $current_list_value = isset( $all_settings[ "{$integration->slug}_{$list_type->id}" ] ) ? $all_settings[ "{$integration->slug}_{$list_type->id}" ] : $list_type->get_default_list_id(); ?>

                                <?php if ( $list_type->is_taggy ) : ?>

                                    <input
                                        type="text"
                                        placeholder="<?php echo esc_attr( $list_type->name ); ?> 1, <?php echo esc_attr( $list_type->name ); ?> 2"
										class="regular-text"
										id="noptin-form-<?php echo esc_attr( $integration->slug ); ?>-<?php echo esc_attr( $list_type->id ); ?>"
										name="noptin_form[settings][<?php echo esc_attr( $integration->slug ); ?>_<?php echo esc_attr( $list_type->id ); ?>]"
										value="<?php echo esc_attr( $current_list_value ); ?>"
										/>

                                    <p class="description">
                                        <?php
                                            printf(
                                                // translators: %s is the list type, %s is the contact type.
                                                esc_html__( 'Comma separated list of %1$s to add to %2$s.', 'newsletter-optin-box' ),
                                                esc_html( strtolower( $list_type->name_plural ) ),
                                                esc_html( strtolower( $integration->subscriber_name_plural ) )
                                            );
                                        ?>
                                    </p>

                                <?php else : ?>

                                    <select class="regular-text" id="noptin-form-<?php echo esc_attr( $integration->slug ); ?>-<?php echo esc_attr( $list_type->id ); ?>" name="noptin_form[settings][<?php echo esc_attr( $integration->slug ); ?>_<?php echo esc_attr( $list_type->id ); ?>]">
                                        <option value="-1" <?php selected( '-1', $current_list_value ); ?>><?php esc_html_e( 'None', 'newsletter-optin-box' ); ?></option>
                                        <?php foreach ( $list_type->get_lists() as $list_key => $list_label ) : ?>
                                            <option value="<?php echo esc_attr( $list_key ); ?>" <?php selected( $list_key, $current_list_value ); ?>><?php echo esc_attr( $list_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <p class="description">
                                        <?php
                                            printf(
                                                // translators: %s is the list type, %s is the contact type.
                                                esc_html__( 'Select the %1$s to add to %2$s.', 'newsletter-optin-box' ),
                                                esc_html( strtolower( $list_type->name ) ),
                                                esc_html( strtolower( $integration->subscriber_name_plural ) )
                                            );
                                        ?>

                                <?php endif; ?>

                            </td>
						</tr>

                    <?php endif; ?>

                <?php endforeach; ?>

            </table>

		<?php endif; ?>

	</div>

</fieldset>

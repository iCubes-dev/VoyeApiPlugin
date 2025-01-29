<?php
/**
 * File responsible for registering a GraphQL mutation for editing user account details.
 *
 * @package VoyeglobalGraphql
 */

namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EditUserAccount
 *
 * Registers the EditUserAccount GraphQL mutation for editing user account details.
 */
class EditUserAccount {

	/**
	 * Initializes the class by registering the mutation.
	 */
	public static function init() {
		add_action( 'graphql_register_types', array( __CLASS__, 'register_mutation' ) );
	}

	/**
	 * Registers the EditUserAccount GraphQL mutation.
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'EditUserAccount',
			array(
				'inputFields'         => array(
					'firstName'  => array(
						'type'        => 'String',
						'description' => __( 'First name of the user.', 'your-text-domain' ),
					),
					'lastName'   => array(
						'type'        => 'String',
						'description' => __( 'Last name of the user.', 'your-text-domain' ),
					),
					'newsletter' => array(
						'type'        => 'Boolean',
						'description' => __( 'Subscription status to newsletter.', 'your-text-domain' ),
					),
				),
				'outputFields'        => array(
					'success' => array(
						'type'        => 'Boolean',
						'description' => __( 'Whether the update was successful.', 'your-text-domain' ),
					),
					'error'   => array(
						'type'        => 'String',
						'description' => __( 'Error message if the operation fails.', 'your-text-domain' ),
					),
				),
				'mutateAndGetPayload' => function( $input ) {
					return self::mutate_and_get_payload( $input );
				},
			)
		);
	}

	/**
	 * Handles the mutation logic and returns the payload.
	 *
	 * @param array $input The input fields for the mutation.
	 * @return array The mutation result, including success status and error message.
	 */
	protected static function mutate_and_get_payload( $input ) {
		$user_id = get_current_user_id();

		// Check if the user is logged in and valid.
		if ( ! $user_id ) {
			return array(
				'success' => false,
				'error'   => __( 'User is not logged in or invalid.', 'your-text-domain' ),
			);
		}

		// Update user data.
		$user_data = array(
			'ID'         => $user_id,
			'first_name' => sanitize_text_field( $input['firstName'] ),
			'last_name'  => sanitize_text_field( $input['lastName'] ),
		);

		// Attempt to update the user.
		$user_id = wp_update_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return array(
				'success' => false,
				'error'   => $user_id->get_error_message(),
			);
		}

		// Update ACF fields or meta fields for newsletter subscription.
		update_field( 'newsletter', $input['newsletter'], 'user_' . $user_id );

		// Trigger actions after account update.
		do_action( 'woocommerce_save_account_details', $user_id );

		return array(
			'success' => true,
			'error'   => null,
		);
	}
}

// Initialize the mutation.
EditUserAccount::init();

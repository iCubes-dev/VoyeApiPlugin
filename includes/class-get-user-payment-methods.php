<?php
/**
 * Class GetUserPaymentMethods
 *
 * @package VoyeglobalGraphql
 */

namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class GetUserPaymentMethods
 *
 * Get user payment methods.
 */
class GetUserPaymentMethods {
    /**
	 * Constructor to registering the query.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_query_for_show_payments' ) );
	}

    /**
     * Registers custom GraphQL types and fields.
     *
     * This function registers a custom object type for saved payment methods and a query field to fetch saved payment methods.
     *
     * @return void
     */
    public static function register_query_for_show_payments() {
        register_graphql_object_type('SavedPaymentMethods', [
            'description' => __('Saved payment methods.', 'voye'),
            'fields' => [
                'savedMethods' => [
                    'type' => ['list_of' => 'String'],
                    'description' => __('List of saved payment methods.', 'voye'),
                ],
                'message' => [
                    'type' => 'String',
                    'description' => __('Message about the saved payment methods.', 'voye'),
                ],
            ],
        ]);

        // Register query field
        register_graphql_field('RootQuery', 'getSavedPaymentMethods', [
            'type'    => 'SavedPaymentMethods',
            'resolve' => [self::class, 'resolveGetSavedPaymentMethods'],
        ]);
    }

    /**
     * Resolver for the `getSavedPaymentMethods` query field.
     *
     * Retrieves the list of saved payment methods for the current user.
     *
     * @param mixed $source Source of the GraphQL query.
     * @param array $args Arguments passed to the query.
     * @param mixed $context Context of the query.
     * @param mixed $info Information about the query.
     * @return array Array containing the saved payment methods and a message.
     */
    public function resolveGetSavedPaymentMethods($source, $args, $context, $info) {
        $current_user_id = get_current_user_id();

        if (!$current_user_id) {
            return [
                'savedMethods' => [],
                'message' => __('User is not logged in.', 'voye'),
            ];
        }

        $saved_methods = wc_get_customer_saved_methods_list($current_user_id);
        $methods_list = [];

        foreach ($saved_methods as $methods) {
            foreach ($methods as $method) {
                $methods_list[] = sprintf(
                    '%s ending in %s',
                    wc_get_credit_card_type_label($method['method']['brand']),
                    $method['method']['last4']
                );
            }
        }

        if (empty($methods_list)) {
            return [
                'savedMethods' => [],
                'message' => __('No saved methods found.', 'voye'),
            ];
        }

        return [
            'savedMethods' => $methods_list,
            'message' => __('Saved methods retrieved successfully.', 'voye'),
        ];
    }
}

// Register custom GraphQL types when GraphQL is initialized.


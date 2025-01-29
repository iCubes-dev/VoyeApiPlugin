<?php
/**
 * WooCommerceCurrenciesQuery class file.
 *
 * @package VoyeglobalGraphql
 */

namespace VoyeglobalGraphql\Includes;

use GraphQL\Type\Definition\Type; // Use the correct GraphQL Type

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerceCurrenciesQuery
 *
 * Registers a custom GraphQL query to fetch active WooCommerce currencies using WCML currency switcher.
 */
class WooCommerceCurrenciesQuery {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', [ $this, 'register_graphql_currencies_query' ] );
	}

	/**
	 * Registers the GraphQL field for WooCommerce active currencies.
	 */
	public function register_graphql_currencies_query() {
		register_graphql_field( 'RootQuery', 'woocommerceCurrencies', [
			'type'        => Type::listOf( Type::string() ), // Use the correct method for array of strings
			'description' => __( 'List of active WooCommerce currencies', 'your-textdomain' ),
			'resolve'     => array( $this, 'get_active_woocommerce_currencies' ),
		] );
	}
	/**
 * Retrieves the active WooCommerce currencies using the WCML currency switcher.
 *
 * @return array
 */
public function get_active_woocommerce_currencies() {
	$result = [];

	// Use wcml_currency_switcher to get active currencies
	ob_start(); // Start output buffering to capture the currency switcher output
	do_action(
		'wcml_currency_switcher',
		array(
			'format'         => '%symbol% - %name%',
			'switcher_style' => 'wcml-vertical-list',
		)
	);
	$currency_switcher_output = ob_get_clean(); // Get the captured output

	// Parse the output (if needed) and add to result array
	// Assuming the output is a list of currencies, format as needed
	if ( ! empty( $currency_switcher_output ) ) {
		// Split the output into lines (adjust if necessary based on the actual HTML structure)
		$currencies = explode( "\n", strip_tags( $currency_switcher_output ) );

		// Remove any empty or whitespace-only lines
		foreach ( $currencies as $currency ) {
			$currency = trim( $currency );
			if ( ! empty( $currency ) ) {
				$result[] = $currency;
			}
		}
	}

	return $result;
}

}

new WooCommerceCurrenciesQuery();

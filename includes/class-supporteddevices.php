<?php
/**
 * Check SupportedDevices mutation.
 *
 * @package VoyeglobalGraphql
 *
 * This file defines the GraphQL mutation for Check SupportedDevices.
 *
 * Example GraphQL Mutation:
 *
 * ```
 * mutation checkSupportedDevices($device_name: String!) {
 * checkSupportedDevices(input: { device_name: $device_name }) {
 *   is_supported
 *   supported_device_names
 *  }
 * }

 * ```
 */

namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SupportedDevices
 *
 * Registers a custom GraphQL mutation to check for supported devices.
 */
class SupportedDevices {

	/**
	 * SupportedDevices constructor.
	 *
	 * Hooks into graphql_register_types to register the mutation.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_mutation' ) );
	}

	/**
	 * Registers the checkSupportedDevices GraphQL mutation.
	 *
	 * The mutation allows checking for supported devices by their name.
	 */
	public function register_mutation() {
		register_graphql_mutation(
			'checkSupportedDevices',
			array(
				'inputFields'         => array(
					'device_name' => array(
						'type'        => 'String',
						'description' => 'The name of the device to search for',
					),
				),
				'outputFields'        => array(
					'is_supported'           => array(
						'type'        => 'Boolean',
						'description' => 'Whether the device is supported or not',
					),
					'supported_device_names' => array(
						'type'        => array( 'list_of' => 'String' ),
						'description' => 'The names of the supported devices',
					),
				),
				'mutateAndGetPayload' => array( $this, 'mutate_and_get_payload' ),
			)
		);
	}

	/**
	 * Handles the checkSupportedDevices mutation payload.
	 *
	 * Searches the supported devices JSON file for the given device name.
	 *
	 * @param array $input The input arguments for the mutation.
	 * @param mixed $context The GraphQL context.
	 * @param mixed $info The GraphQL resolve info.
	 *
	 * @return array The result of the mutation, including whether the device is supported and the names of supported devices.
	 */
	public function mutate_and_get_payload( $input, $context, $info ) {
		$device_name            = strtolower( trim( $input['device_name'] ) );
		$is_supported           = false;
		$supported_device_names = array();
		$file_path              = VOYEGLOBALGRAPHQL_DATA_PATH . 'supported_devices.json';
		if ( ! file_exists( $file_path ) ) {
			return array(
				'is_supported'           => $is_supported,
				'supported_device_names' => $supported_device_names,
			);
		}
		$data = json_decode( file_get_contents( $file_path ), true );

		if ( $data ) {
			foreach ( $data as $brand ) {
				foreach ( $brand['models'] as $model ) {
					$model_name = strtolower( $model['model_name'] );
					if ( strpos( $model_name, $device_name ) !== false ) {
						$is_supported             = true;
						$supported_device_names[] = $model['model_name'];
					}
				}
			}
		}

		return array(
			'is_supported'           => $is_supported,
			'supported_device_names' => $supported_device_names,
		);
	}
}

new SupportedDevices();


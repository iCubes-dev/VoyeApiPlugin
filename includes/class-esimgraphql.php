<?php
/**
 * Provide query for esim plans for user.
 *
 * @package VoyeglobalGraphql
 */

namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class EsimGraphQL
 *
 * Handles eSIM Plan GraphQL types and queries.
 */
class EsimGraphQL {
	/**
	 * EsimGraphQL constructor.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_esim_plan_type' ) );
		// add_action( 'graphql_register_types', array( $this, 'register_esim_plans_query' ) );
		add_action( 'graphql_register_types', array( $this, 'renameEsimMutations' ) );
		add_action('graphql_register_types', array( $this, 'esimDataStructure' ) );
		add_action( 'graphql_register_types', array( $this, 'getEsimsByUser' ) );
		
		
	}

	
	

	/**
	 * Registers the eSIM Plan GraphQL type.
	 *
	 * @return void
	 */
	
	public function register_esim_plan_type() {
		register_graphql_object_type(
			'EsimPlan',
			array(
				'description' => 'Represents an eSIM plan.',
				'fields'      => array(
					'planId'         => array(
						'type'        => 'String',
						'description' => 'The ID of the plan.',
					),
					'planActive'     => array(
						'type'        => 'Boolean',
						'description' => 'Whether the plan is active.',
					),
					'usageData'      => array(
						'type'        => 'Int',
						'description' => 'The amount of usage data for the plan.',
					),
					'status'         => array(
						'type'        => 'String',
						'description' => 'The status of the plan.',
					),
					'activationDate' => array(
						'type'        => 'String',
						'description' => 'The activation date of the plan.',
					),
					'vdata'=> array(
						'type'        => 'String',
						'description' => 'The activation date of the plan.',
					),
				),
			)
		);
	}

	/**
	 * Registers the query to fetch eSIM plans.
	 *
	 * @return void
	 */
	

	public function renameEsimMutations() {
		
		register_graphql_mutation(
			'changeEsimName', 
			[
				'inputFields' => [
					'esimId' => [
						'type'        => 'String',
						'description' => 'The ID of the eSIM to be updated',
					],
					'name' => [
						'type'        => 'String',
						'description' => 'The new name for the eSIM',
					],
				],
				'outputFields' => [
					'success' => [
						'type'        => 'Boolean',
						'description' => 'Whether the eSIM name was successfully changed',
					],
					'message' => [
						'type'        => 'String',
						'description' => 'A message describing the result',
					],
				],
				'mutateAndGetPayload' => function( $input ) {
					$esim_id = isset( $input['esimId'] ) ? sanitize_text_field( $input['esimId'] ) : null;
					$name    = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : null;

					$user_id = get_current_user_id();
					
					// echo WC()->session->get('custom_data'); die;

					if ( ! $user_id ) {
						return [
							'success' => false,
							'message' => 'Please authenticate first',
						];
					}
					
					if ( $esim_id && $name ) {
						if ( class_exists( 'Webbing_Int' ) ) {
							$webbing_db_plugin = new \Webbing_Int_Db();
							$updated = $webbing_db_plugin->change_esim_name( $esim_id, $name );
							
							if ( $updated ) {
								return [
									'success' => true,
									'message' => 'eSIM name successfully updated',
								];
							} else {
								return [
									'success' => false,
									'message' => 'Failed to update the eSIM name',
								];
							}
						} else {
							return [
								'success' => false,
								'message' => 'Webbing plugin not found',
							];
						}
					} else {
						return [
							'success' => false,
							'message' => 'Invalid eSIM ID or name',
						];
					}
				},
			]
		);
	}


	public function allEsimsPlansMutations() {

		register_graphql_object_type('PlanDetails', [
			'description' => 'Details of individual plans assigned to eSIM.',
			'fields' => [
				'servicePlanId'     => ['type' => 'String'],
				'planId'            => ['type' => 'String'],
				'esimId'            => ['type' => 'String'],
				'esim_name'         => ['type' => 'String'],
				'planActive'        => ['type' => 'Boolean'],
				'usageData'         => ['type' => 'String'],
				'status'            => ['type' => 'String'],
				'activationDate'    => ['type' => 'String'],
				'plan_valid_until'  => ['type' => 'String'],
				'data_quota'        => ['type' => 'String'],
			],
		]);
		register_graphql_field('RootQuery', 'getAllEsimsPlans', [
			'type' => ['list_of' => 'PlanDetails'],
			'resolve' => function() {
				
				$user_id = 528;
				// $user_id = get_current_user_id();
				if (!$user_id) {
					return new WP_Error( 'authentication_error', 'Please authenticate first' );
				}

				
				$plans = $this->get_all_esims_and_plans( $user_id ); 
				// print_r($plans); 
            	// die('stop1233');


				if (isset($plans[0]['assignedPlans']) && !empty($plans[0]['assignedPlans'])) {

					$planDetails = [];
					foreach ($plans as $esim) {
						$esim_id = $esim['esimId'];  
						$esim_name = $esim['esimName'];
						if (isset($esim['assignedPlans'])) {
							foreach ($esim['assignedPlans'] as $plan) {
								
								$planDetails[] = [
									'servicePlanId' => $plan['service_plan_id'],
									'planId' => $plan['plan_id'],
									'esimId' => $esim['esimId'],
									'esim_name' => $esim['esimName'],
									'planActive' => isset($plan['plan_active']) ? $plan['plan_active'] : null,
									'usageData' => isset($plan['usage_data']) ? $plan['usage_data'] : null,
									'status' => isset($plan['status']) ? $plan['status'] : null,
									'activationDate' => isset($plan['activation_date']) ? $plan['activation_date'] : null,
									'plan_valid_until' => isset($plan['plan_valid_until']) ? $plan['plan_valid_until'] : null,
									'data_quota' => isset($plan['data_quota']) ? $plan['data_quota'] : null,
								];
							}
						}
					}
					return $planDetails;
				} else {
						return [
							'success' => false,
							'message' => 'No plans found or failed to retrieve data',
						];
					}
				},
		]);
	}

	
	public function bytes_to_gb($bytes) {
		$bytes = str_replace( ',', '', $bytes );

		$gb = $bytes / ( 1024 * 1024 * 1024 );
		// Format to two decimal places
		$formatted_gb = number_format( $gb, 2 );
		return $formatted_gb;
	}


	public function get_all_esims_and_plans($user_id) {

		if ( ! class_exists( 'Webbing_Int' ) ) {
			return new WP_Error( 'plugin_missing', 'Webbing plugin not found', [ 'status' => 404 ] );
		}
	
		$webbing_plugin = new \Webbing_Int();
		$customer_esims = $webbing_plugin->get_customer_esims( $user_id );
	
		if ( empty( $customer_esims ) ) {
			return [];
		}
	
		$response_data = [];
	

		foreach ( $customer_esims as $customer_esim ) {
			$esim_id = $customer_esim['service_id'];
			$assigned_plans = [];
			

			$usage = $webbing_plugin->get_customer_plan_device_usage( $esim_id );
			$sim_activated = $webbing_plugin->check_esim_status( $esim_id );
			$esim_plans = $webbing_plugin->get_customer_plans_by_esim( $esim_id );
	
			foreach ($esim_plans as $esim_plan) {
				
				if ((int) $esim_plan['user_id'] !== $user_id) {
					continue;
				}
				$plan_id         = strtolower($esim_plan['plan_id']);
				$service_plan_id1 = strtolower($esim_plan['plan_id']);
				$service_plan_id1 = preg_replace('/\x{00A0}/u', ' ', $service_plan_id1);
				$service_plan_id1 = trim(mb_convert_encoding($service_plan_id1, 'UTF-8', 'auto'));

				if (! isset($esim_include_ar[$service_plan_id1])) {
					
					$assigned_plans[] = array(
						'service_plan_id' => $service_plan_id1,
						'plan_id'         => $service_plan_id1,
						'plan_active'     => false,
						'usage_data'      => '',
						'usage_data_mb'   => '',
						'status'          => 'Not Active',
						'total_data' 	  => '',
						'valid_for' 	  => '',
						'percentage' 	  => '',
						'data_left'		  => '',
						'activation_date' => '',
						'expire_data'	  => '',
					);
				}
			}

			if (isset($usage[0])) {

				foreach ($usage as $plan_usage) {

					if (is_array($plan_usage) && empty($esim_include_ar[$plan_usage['CustomerPlanID']]) &&  false === strpos($plan_usage['name'], 'Global - 365Days -100MB')) {

						$plan_active     = $plan_usage['Status'] == 'Active' ?  true : false;
						$usage_data      = $plan_usage['Usage'];
						$status          = $plan_usage['Status'];
						$activation_date = $plan_usage['ActivationTime'];
						$service_plan_id = $plan_usage['ServiceDeviceCustomerPlanID'];
						$plan_id = $plan_usage['CustomerPlanID'];

						$assigned_plans[] = array(
							'service_plan_id' => $service_plan_id,
							'plan_id'         => $plan_id,
							'plan_active'     => $plan_active,
							'usage_data'      => $usage_data ,
							'usage_data_mb'   => $this->bytes_to_gb( (int) $usage_data ),
							'status'          => $status,
							'total_data' 			  => '',
							'valid_for' 	  => '',
							'percentage' 	  => '',
							'data_left'		  => '',
							'activation_date' => $activation_date,
							'expire_data'	  => '',
						);
					}
				}
			} elseif (isset($usage['CustomerPlanID'])) {



				if (empty($esim_include_ar[strtolower($usage['CustomerPlanID'])]) &&  false === strpos($usage['Name'], 'Global - 365Days -100MB')) {
					$plan_active     = $usage['Status'] == 'Active' ?  true : false;
					$usage_data      = $usage['Usage'];
					$status          = $usage['Status'];
					$activation_date = $usage['ActivationTime'];
					$service_plan_id = $usage['ServiceDeviceCustomerPlanID'];

					$assigned_plans[] = array(
						'service_plan_id' => $service_plan_id,
						'plan_id'         => $plan_id,
						'plan_active'     => $plan_active,
						'usage_data'      => $usage_data ,
						'usage_data_mb'   => $this->bytes_to_gb( (int) $usage_data ),
						'status'          => $status,
						'total_data' 			  => '',
						'valid_for' 	  => '',
						'percentage' 	  => '',
						'data_left'		  => '',
						'activation_date' => $activation_date,
						'expire_data'	  => '',
					);
				}
			}

			usort(
				$assigned_plans,
				function ($a, $b) {
					return ($b['plan_active'] - $a['plan_active']);
				}
			);

			foreach ($assigned_plans as &$assigned_plan ) {
				$plan_id          = $assigned_plan['plan_id'];
				$plan_active      = $assigned_plan['plan_active'];
				$plan_status      = $assigned_plan['status'];

				$plan_usage_bytes = $this->bytes_to_gb( (int) $assigned_plan['usage_data'] );
				$activation_date  = new \DateTime( $assigned_plan['activation_date'] );

				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => 1,
					'meta_key'       => 'customerplanid',
					'meta_value'     => $plan_id,
				);

				$query = new \WP_Query( $args );

				while ( $query->have_posts() ) :
					$query->the_post();
					$product      = wc_get_product( get_the_ID() );
					$category_ids = $product->get_category_ids();

					if ( ! empty( $category_ids ) ) {
						$first_category = get_term_by( 'id', $category_ids[0], 'product_cat' );
					}

					$thumbnail_id = get_term_meta( $first_category->term_id, 'thumbnail_id', true );
					$data         = get_field( 'data' );
					$valid_for    = get_field( 'valid_for' );

					if($data === "0" || $data === 0){
						$percentage = 0;
					}else{
						$percentage   = number_format( ( $plan_usage_bytes / $data ) * 100, 2 );
					}
					$data_used   =  $plan_usage_bytes;

					 // Update the current $assigned_plan
					 $assigned_plan['total_data'] = $data;      
					 $assigned_plan['valid_for']  = $valid_for;
					 $assigned_plan['percentage'] = $percentage;
					 $assigned_plan['data_left'] = $data - $plan_usage_bytes . 'GB';
					 $assigned_plan['expire_data'] = $activation_date->modify( '+' . $valid_for . ' days' )->format( 'd/m/Y' );

				endwhile;
				wp_reset_postdata();

			}

			unset($assigned_plan);
	

			$response_data[] = [
				'esimId' => $esim_id,
				'esimName' => $customer_esim['name'],
				'simActivated' => $sim_activated,
				'assignedPlans' => $assigned_plans
			];
		}
	
		return $response_data;
	}


	public function esimDataStructure() {
		// Register the individual eSIM type
		register_graphql_object_type('EsimType', [
			'description' => 'Details of an eSIM',
			'fields' => [
				'esim_id' => [
					'type' => 'String',
					'description' => 'The ID of the eSIM',
				],
				'esim_name' => [
					'type' => 'String',
					'description' => 'The name of the eSIM',
				],
				'is_activated' => [
					'type' => 'Boolean',
					'description' => 'Whether the eSIM is activated',
				],
				'free_service_plan_id' => [
					'type' => 'String',
					'description' => 'The Service Plan of the eSIM',
				],
				'service_plan_id' => [
					'type' => 'String',
					'description' => 'The Service Plan of the eSIM',
				],
				'plan_id' => [
					'type' => 'String',
					'description' => 'The Service Plan id of the eSIM',
				],
				
			],
		]);
	
		// Register the overall response type
		register_graphql_object_type('EsimsResponse', [
			'description' => 'Response structure for eSIMs',
			'fields' => [
				'success' => [
					'type' => 'Boolean',
					'description' => 'Whether the operation was successful',
				],
				'message' => [
					'type' => 'String',
					'description' => 'Additional information about the response',
				],
				'esims' => [
					'type' => ['list_of' => 'EsimType'],
					'description' => 'List of eSIMs',
				],
			],
		]);
	}


	public function getEsimsByUser() {
		register_graphql_field('RootQuery', 'getEsimsByUser', [
			'type' => 'EsimsResponse', // Use the new response type
			'resolve' => function () {
				// $user_id = get_current_user_id();
				$user_id = 44;
				
				global $wpdb;
				$table_name = $wpdb->prefix . 'webbing_customer_plans';
	
				if (!$user_id || $user_id == 0) {
					return [
						'success' => false,
						'message' => 'Please authenticate first',
						'esims' => [],
					];
				}
	
				$webbing_plugin = new \Webbing_Int();
				$customer_esims = $webbing_plugin->get_customer_esims($user_id);
	
				if (empty($customer_esims)) {
					return [
						'success' => false,
						'message' => 'No eSIMs found for the user',
						'esims' => [],
					];
				}
	
				$response_data = [];
				foreach ($customer_esims as $customer_esim) {
					$esim_id = $customer_esim['service_id'];
					$esim_name = $customer_esim['name'];
					$sim_activated = $webbing_plugin->check_esim_status($esim_id);

					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM $table_name WHERE esim_id = %d AND user_id = %d", 
							$esim_id, 
							$user_id
						),
						ARRAY_A
					);

					$free_service_plan_id = !empty($results) ? $results[0]['free_service_plan_id'] : null;
					$plan_id = !empty($results) ? $results[0]['plan_id'] : null;
					$service_plan_id = !empty($results) ? $results[0]['service_plan_id'] : null;
					
					$response_data[] = [
						'esim_id' => $esim_id,
						'esim_name' => $esim_name,
						'is_activated' => $sim_activated,
						'free_service_plan_id' => $free_service_plan_id,
						'service_plan_id' => $service_plan_id, 
						'plan_id' => $plan_id,
					];
				}
	
				return [
					'success' => true,
					'message' => 'eSIMs retrieved successfully',
					'esims' => $response_data,
				];
			},
		]);
	}
	

	


}
new EsimGraphQL();


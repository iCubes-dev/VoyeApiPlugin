<?php
/**
 * EsimProductsQuery class file.
 *
 * @package VoyeglobalGraphql
 */

 namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EsimProductsQuery
 *
 * Registers a custom GraphQL query to fetch products by eSIM category.
 */
class EsimProductsQuery {

	/**
	 * EsimProductsQuery constructor.
	 *
	 * Hooks into graphql_register_types to register the query.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_query' ) );
	}

	/**
	 * Registers the esimProducts GraphQL query.
	 */
	public function register_query() {
		register_graphql_field(
			'RootQuery',
			'esimProducts',
			array(
				'type'        => array( 'list_of' => 'EsimProduct' ),
				'description' => __( 'Fetch products by eSIM category', 'voye' ),
				'args'        => array(
					'type'     => array(
						'type'        => 'String',
						'description' => __( 'Type of eSIM category (local, global, regional)', 'voye' ),
					),
					'category' => array(
						'type'        => 'String',
						'description' => __( 'The name of the eSIM category', 'voye' ),
					),
					'currency' => array(
						'type'        => 'String',
						'description' => __( 'The currency to display the product prices in, defaults to USD', 'voye' ),
					),
					'place'    => array(
						'type'        => 'String',
						'description' => __( 'The place to fetch coverage info for', 'voye' ),
					),
				),
				'resolve'     => array( $this, 'resolve_esim_products' ),
			)
		);
		register_graphql_object_type(
			'CountryInfo',
			array(
				'fields' => array(
					'country_name' => array(
						'type'        => 'String',
						'description' => __( 'Name of the country', 'voye' ),
					),
					'country_flag' => array(
						'type'        => 'String',
						'description' => __( 'URL of the country flag', 'voye' ),
					),
				),
			)
		);
		register_graphql_object_type(
			'EsimProduct',
			array(
				'description' => __( 'A product in the eSIM category', 'voye' ),
				'fields'      => array(
					'id'             => array(
						'type'        => 'Integer',
						'description' => __( 'The id of the product', 'voye' ),
					),
					'data'           => array(
						'type'        => 'String',
						'description' => __( 'The data allowance of the product', 'voye' ),
					),
					'valid_for'      => array(
						'type'        => 'String',
						'description' => __( 'The validity period of the product', 'voye' ),
					),
					'price'          => array(
						'type'        => 'String',
						'description' => __( 'The price of the product', 'voye' ),
					),
					'coverage'       => array(
						'type'        => 'String',
						'description' => __( 'Coverage information based on the place', 'voye' ),
					),
					'countries_name' => array(
						'type'        => array( 'list_of' => 'CountryInfo' ),
						'description' => __( 'List of countries with their flags', 'voye' ),
					),
					'country_flag'   => array(
						'type'        => 'String',
						'description' => __( 'The country flag for the eSIM category', 'voye' ),
					),
					'country_image'  => array(
						'type'        => 'String',
						'description' => __( 'The first product featured image', 'voye' ),
					),
				),
			)
		);
	}

	/**
	 * Resolves the esimProducts query.
	 *
	 * @param array $root The root query.
	 * @param array $args The query arguments.
	 *
	 * @return array The product details in the specified eSIM category.
	 */
	public function resolve_esim_products( $root, $args ) {
		global $global_countries;

		$cache_key   = 'esim_products_' . md5( wp_json_encode( $args ) );
		$cache_group = 'voyeglobal_graphql';
		$products    = wp_cache_get( $cache_key, $cache_group );
		$products    = false;
		if ( false === $products ) {
			$args = wp_parse_args(
				$args,
				array(
					'currency' => 'USD',
					'place'    => 'local',
				)
			);

			if ( ! empty( $args['category'] ) ) {
				$category = get_term_by( 'name', $args['category'], 'product_cat' );

				if ( ! $category ) {
					return array();
				}
				$category_flag_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
				$country_flag_url = $category_flag_id ? wp_get_attachment_url( $category_flag_id ) : '';

				$query_args = array(
					'post_type'      => 'product',
					'posts_per_page' => -1,
					'meta_key'       => '_price',
					'orderby'        => 'meta_value_num',
					'order'          => 'ASC',
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $category->term_id,
						),
					),
				);

				$product_query = new \WP_Query( $query_args );

				$currency = $args['currency'];
				$place    = $args['place'];
				$products = array();

				while ( $product_query->have_posts() ) {
					$product_query->the_post();
					global $product;
					$coverage       = $this->get_coverage( $place, $args['category'], $product );
					$country_lists  = $this->get_country_names( $place, $args['category'], $product );
					$countries_info = array();
					foreach ( $country_lists as $term_name ) {
						$term = get_term_by( 'name', $term_name, 'product_cat' );

						if ( $term ) {
							$category_flag_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
							$country_flag_url = $category_flag_id ? wp_get_attachment_url( $category_flag_id ) : '';

							// Add combined country info to the array
							$countries_info[] = array(
								'country_name' => $term_name,
								'country_flag' => $country_flag_url,
							);
						}
					}
					$country_image = has_post_thumbnail( get_the_ID() ) ? get_the_post_thumbnail_url( get_the_ID(), 'full' ) : '';
					$products[]    = array(
						'id'             => get_the_ID(),
						'data'           => esc_html( get_field( 'data' ) ) . ' ' . __( 'GB', 'voye' ),
						'valid_for'      => esc_html( get_field( 'valid_for' ) ) . ' ' . __( 'Days', 'voye' ),
						'price'          => wp_strip_all_tags( html_entity_decode( apply_filters( 'wcml_formatted_price', $product->get_price(), $currency ) ) ),
						'coverage'       => $coverage,
						'countries_name' => $countries_info,
						'country_flag'   => $country_flag_url,
						'country_image'  => $country_image,
					);
				}
				wp_reset_postdata();
			}

			wp_cache_set( $cache_key, $products, $cache_group, HOUR_IN_SECONDS );
		}

		return $products;
	}

	/**
	 * Gets coverage information based on the place.
	 *
	 * @param string $place The place to get coverage info for.
	 * @param string $category_name The name of the product category.
	 * @param object $product The product object.
	 *
	 * @return string Coverage information.
	 */
	private function get_coverage( $place, $category_name = null, $product = null ) {
		global $global_countries;

		switch ( $place ) {
			case 'global':
				return $global_countries ? sprintf( '%d %s', count( $global_countries ), __( 'Countries', 'voye' ) ) : '';
			case 'regional':
				if ( $product ) {
					$category_ids = $product->get_category_ids();
					if ( ! empty( $category_ids ) ) {
						$first_category = get_term_by( 'id', $category_ids[0], 'product_cat' );
						$place          = get_field( 'place', 'product_cat_' . $first_category->term_id );
						if ( 'regional' === $place ) {
							$local_cats = get_terms(
								array(
									'taxonomy'   => 'product_cat',
									'hide_empty' => false,
									'fields'     => 'ids',
									'meta_query' => array(
										array(
											'key'     => 'regional_category',
											'value'   => $first_category->term_id,
											'compare' => 'LIKE',
										),
									),
								)
							);
							return sprintf( '%d %s', count( $local_cats ), __( 'Countries', 'voye' ) );
						}
					}
				}
				return __( 'No specific coverage info available', 'voye' );
			case 'local':
				return esc_html( $category_name );
			default:
				return __( 'No specific coverage info available', 'voye' );
		}
	}

	/**
	 * Gets country names based on the place.
	 *
	 * @param string $place The place to get country names for.
	 * @param string $category_name The name of the product category.
	 * @param object $product The product object.
	 *
	 * @return array List of country names.
	 */
	public function get_country_names( $place, $category_name = null, $product = null ) {
		global $global_countries;

		switch ( $place ) {
			case 'global':
				return wp_list_pluck( $global_countries, 'name' );
			case 'regional':
				if ( $product ) {
					$category_ids = $product->get_category_ids();
					if ( ! empty( $category_ids ) ) {
						$first_category = get_term_by( 'id', $category_ids[0], 'product_cat' );
						$place          = get_field( 'place', 'product_cat_' . $first_category->term_id );
						if ( 'regional' === $place ) {
							return get_terms(
								array(
									'taxonomy'   => 'product_cat',
									'hide_empty' => false,
									'fields'     => 'names',
									'meta_query' => array(
										array(
											'key'     => 'regional_category',
											'value'   => $first_category->term_id,
											'compare' => 'LIKE',
										),
									),
								)
							);
						}
					}
				}
				return array( __( 'No country info available', 'voye' ) );
			case 'local':
				return array( $category_name );
			default:
				return array( __( 'No country info available', 'voye' ) );
		}
	}
}

new EsimProductsQuery();

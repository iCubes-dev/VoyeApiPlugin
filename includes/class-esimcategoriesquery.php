<?php
/**
 * EsimCategoriesQuery class file.
 *
 * This file contains the definition of the `EsimCategoriesQuery` class which
 * registers a custom GraphQL query to fetch eSIM categories based on place.
 *
 * Use as:
 *
 * query FetchEsimCategories($place: String!) {
 *   esimCategories(place: $place) {
 *     name
 *     image
 *     lowest_price
 *   }
 * }
 *
 * This GraphQL query retrieves a list of eSIM categories based on the specified place.
 *
 * @package VoyeglobalGraphql
 */

namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class EsimCategoriesQuery.
 *
 * Registers a custom GraphQL query to fetch eSIM categories based on place.
 */
class EsimCategoriesQuery {

	/**
	 * EsimCategoriesQuery constructor.
	 *
	 * Hooks into graphql_register_types to register the query.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_query' ) );
	}

	/**
	 * Registers the esimCategories GraphQL query.
	 *
	 * The query fetches eSIM categories based on the specified place.
	 */
	public function register_query() {
		register_graphql_field(
			'RootQuery',
			'esimCategories',
			array(
				'type'        => array( 'list_of' => 'EsimCategory' ),
				'description' => __( 'Fetch eSIM categories based on place', 'voye' ),
				'args'        => array(
					'place' => array(
						'type'        => 'String',
						'description' => __( 'Place identifier (local, regional, global)', 'voye' ),
					),
				),
				'resolve'     => array( $this, 'resolve_esim_categories' ),
			)
		);

		register_graphql_object_type(
			'EsimCategory',
			array(
				'description' => __( 'eSIM category type', 'voye' ),
				'fields'      => array(
					'name'                => array(
						'type'        => 'String',
						'description' => __( 'The name of the category', 'voye' ),
					),
					'image'               => array(
						'type'        => 'String',
						'description' => __( 'The URL of the category image', 'voye' ),
					),
					'lowest_price'        => array(
						'type'        => 'String',
						'description' => __( 'The lowest price of products in the category', 'voye' ),
					),
					'supported_countries' => array(
						'type'        => array( 'list_of' => 'String' ),
						'description' => __( 'List of supported countries for this eSIM category', 'voye' ),
					),
					'supported_plans'     => array(  // New field for supported plans count
						'type'        => 'Int',
						'description' => __( 'Count of products in this eSIM category', 'voye' ),
					),
				),
			)
		);
	}

	/**
	 * Resolves the esimCategories query based on place.
	 *
	 * @param array $root The query root.
	 * @param array $args The query arguments.
	 * @return array The names of eSIM categories for the specified place.
	 */
	public function resolve_esim_categories( $root, $args ) {
		$place      = isset( $args['place'] ) ? $args['place'] : 'local';
		$categories = array();

		$categories_terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'fields'     => 'all',
				'meta_query' => array(
					array(
						'key'   => 'place',
						'value' => $place,
					),
				),
			)
		);

		if ( ! is_wp_error( $categories_terms ) ) {
			foreach ( $categories_terms as $term ) {
				$lowest_price = null;

				// Query products in the current category
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => -1,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'id',
							'terms'    => $term->term_id,
						),
					),
				);

				$products_query = new \WP_Query( $args );

				// Find the lowest price in the category
				if ( $products_query->have_posts() ) {
					$product_count = $products_query->found_posts;
					while ( $products_query->have_posts() ) {
						$products_query->the_post();
						$product = wc_get_product( get_the_ID() );
						$price   = $product->get_price();
						if ( null === $lowest_price || $price < $lowest_price ) {
							$lowest_price = $price;
						}
					}
					wp_reset_postdata();
				}

				$lowest_price    = ( null === $lowest_price ? 0 : $lowest_price );
				$currency_symbol = '';
				if ( class_exists( 'WC' ) ) {
					$currency_symbol = get_woocommerce_currency_symbol();
				}
				$formatted_price = sprintf( '%s %.2f', $currency_symbol, (float) $lowest_price );

				// Fetch the category image
				$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
				$image_url    = wp_get_attachment_url( $thumbnail_id );

				$esim_products_query = new EsimProductsQuery();
				$supported_countries = $esim_products_query->get_country_names( $place, $term->slug, $product ); // Fetch supported countries based on place and category slug

				$categories[] = array(
					'name'                => $term->name,
					'image'               => $image_url,
					'lowest_price'        => $formatted_price,
					'supported_countries' => $supported_countries,
					'supported_plans'     => $product_count,
				);
			}
		}
		return $categories;
	}
}

new EsimCategoriesQuery();

<?php
/**
 * PopularPlans class file.
 *
 * Use as:
 *
 * Query:
 * {
 *   popularPlansAndPrices {
 *     name
 *     lowest_price
 *     image
 *     thumbnail
 *     term_link
 *   }
 * }
 *
 * This GraphQL query retrieves a list of popular plans with their lowest prices, including:
 * - `name`: The name of the popular plan.
 * - `lowest_price`: The lowest price of the plan formatted with the currency symbol.
 * - `image`: The URL of the plan's image.
 * - `thumbnail`: The URL of the plan's thumbnail image.
 * - `term_link`: The URL link to the term (category) page for the plan.
 *
 * @package VoyeglobalGraphql
 */

 namespace VoyeglobalGraphql\Includes;

 if ( ! defined( 'ABSPATH' ) ) {
	 exit;
 }


/**
 * Class PopularPlans
 *
 * Registers GraphQL types and resolves popular plans and prices.
 */
class PopularPlans {

	/**
	 * Constructor to add action for registering GraphQL types.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_graphql_types' ) );
	}

	/**
	 * Registers GraphQL object type and query field.
	 */
	public function register_graphql_types() {
		// Define the PopularPlan type.
		register_graphql_object_type(
			'PopularPlan',
			array(
				'description' => __( 'A popular plan with its lowest price.', 'your-text-domain' ),
				'fields'      => array(
					'name'         => array( 'type' => 'String' ),
					'lowest_price' => array( 'type' => 'String' ),
					'image'        => array( 'type' => 'String' ),
					'thumbnail'    => array( 'type' => 'String' ),
					'term_link'    => array( 'type' => 'String' ),
				),
			)
		);

		// Register the query.
		register_graphql_field(
			'RootQuery',
			'popularPlansAndPrices',
			array(
				'type'        => array( 'list_of' => 'PopularPlan' ),
				'description' => __( 'List of popular plans with their lowest prices.', 'your-text-domain' ),
				'resolve'     => array( $this, 'resolve_popular_plans_and_prices' ),
			)
		);
	}

	/**
	 * Resolves the popular plans and their lowest prices.
	 *
	 * @param mixed $root    The query root.
	 * @param array $args    The query arguments.
	 * @param mixed $context The query context.
	 * @param mixed $info    The query info.
	 *
	 * @return array|null List of popular plans with their lowest prices, or null if no plans.
	 */
	public function resolve_popular_plans_and_prices( $root, $args, $context, $info ) {
		$cache_key      = 'popular_plans_and_prices';
		$cache_group    = 'voyeglobal_graphql';
		$cached_results = wp_cache_get( $cache_key, $cache_group );

		if ( false !== $cached_results ) {
			return $cached_results;
		}

		// Use the homepage ID to get the ACF field.
		$homepage_id = get_option( 'page_on_front' );
		$plans       = get_field( 'plans', $homepage_id );

		if ( ! $plans ) {
			return null;
		}

		$results = array();
		foreach ( $plans as $plan ) {
			$lowest_price = null;
			$thumbnail_id = get_term_meta( $plan->term_id, 'thumbnail_id', true );
			$image        = get_field( 'image', 'product_cat_' . $plan->term_id );
			$args         = array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'id',
						'terms'    => $plan->term_id,
					),
				),
			);

			$products_query = new \WP_Query( $args );

			if ( $products_query->have_posts() ) {
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

			$results[] = array(
				'name'         => $plan->name,
				'lowest_price' => $formatted_price,
				'image'        => wp_get_attachment_image_src( $image, 'home-plan' )[0],
				'thumbnail'    => wp_get_attachment_image_src( $thumbnail_id, 'full' )[0],
				'term_link'    => get_term_link( $plan, 'product_cat' ),
			);
		}

		// Cache the results for 1 hour.
		wp_cache_set( $cache_key, $results, $cache_group, HOUR_IN_SECONDS );

		return $results;
	}
}

new PopularPlans();

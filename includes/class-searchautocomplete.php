<?php
/**
 * Search Auto complete mutation.
 *
 * @package VoyeglobalGraphql
 *
 * This file defines the GraphQL mutation for search autocomplete.
 *
 * Example GraphQL Mutation:
 *
 * ```
 * mutation SearchAutocomplete($inputValue: String!, $language: String!) {
 *   searchAutocomplete(input: {inputValue: $inputValue, language: $language}) {
 *     localResults {
 *       name
 *       thumbnail
 *       link
 *       term_id
 *     }
 *     regionalResults {
 *       name
 *       thumbnail
 *       link
 *       term_id
 *     }
 *     globalResults {
 *       name
 *       thumbnail
 *       link
 *       term_id
 *     }
 *   }
 * }
 * ```
 */

namespace VoyeglobalGraphql\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SearchAutocomplete.
 *
 * Handles the registration of GraphQL types and mutations for search autocomplete.
 */
class SearchAutocomplete {
	/**
	 * SearchAutocomplete constructor.
	 */
	public function __construct() {
		add_action( 'graphql_register_types', array( $this, 'register_graphql_types' ) );
	}
	/**
	 * Registers the GraphQL types and mutations.
	 */
	public function register_graphql_types() {
		// Register the SearchResult type.
		register_graphql_object_type(
			'SearchResult',
			array(
				'description' => __( 'Search result item', 'voyeglobalgraphql' ),
				'fields'      => array(
					'name'      => array(
						'type'        => 'String',
						'description' => __( 'The name of the category', 'voyeglobalgraphql' ),
					),
					'thumbnail' => array(
						'type'        => 'String',
						'description' => __( 'The thumbnail of the category', 'voyeglobalgraphql' ),
					),
					'link'      => array(
						'type'        => 'String',
						'description' => __( 'The link to the category', 'voyeglobalgraphql' ),
					),
					'term_id'   => array(
						'type'        => 'Int',
						'description' => __( 'The term ID of the category', 'voyeglobalgraphql' ),
					),
				),
			)
		);

		// Register the searchAutocomplete mutation.
		register_graphql_mutation(
			'searchAutocomplete',
			array(
				'inputFields'         => array(
					'inputValue' => array(
						'type'        => 'String',
						'description' => __( 'The input value for the search', 'voyeglobalgraphql' ),
					),
					'language'   => array(
						'type'        => 'String',
						'description' => __( 'The current language', 'voyeglobalgraphql' ),
					),
				),
				'outputFields'        => array(
					'localResults'    => array(
						'type'        => array( 'list_of' => 'SearchResult' ),
						'description' => __( 'The local search results', 'voyeglobalgraphql' ),
					),
					'regionalResults' => array(
						'type'        => array( 'list_of' => 'SearchResult' ),
						'description' => __( 'The regional search results', 'voyeglobalgraphql' ),
					),
					'globalResults'   => array(
						'type'        => array( 'list_of' => 'SearchResult' ),
						'description' => __( 'The global search results', 'voyeglobalgraphql' ),
					),
				),
				'mutateAndGetPayload' => array( $this, 'search_autocomplete' ),
			)
		);
	}
	/**
	 * Handles the search autocomplete mutation.
	 *
	 * @param array  $input   The input values for the mutation.
	 * @param object $context The GraphQL context.
	 * @param object $info    The GraphQL info object.
	 *
	 * @return array The search results.
	 */
	public function search_autocomplete( $input, $context, $info ) {
		$inputValue      = strtolower( trim( $input['inputValue'] ) );
		$language        = $input['language'];
		$localResults    = array();
		$regionalResults = array();
		$globalResults   = array();

		if ( strlen( $inputValue ) < 3 ) {
			return array(
				'localResults'    => $localResults,
				'regionalResults' => $regionalResults,
				'globalResults'   => $globalResults,
			);
		}

		$filePath = VOYEGLOBALGRAPHQL_DATA_PATH . "product_categories_{$language}.json";

		if ( ! file_exists( $filePath ) ) {
			return array(
				'localResults'    => $localResults,
				'regionalResults' => $regionalResults,
				'globalResults'   => $globalResults,
			);
		}

		$data = json_decode( file_get_contents( $filePath ), true );

		if ( null === $data ) {
			return array(
				'localResults'    => $localResults,
				'regionalResults' => $regionalResults,
				'globalResults'   => $globalResults,
			);
		}

		$globalExclude      = false;
		$regionalCategories = array();

		foreach ( $data as $cat ) {
			$catName = strtolower( $cat['name'] );
			$isAlt   = false;

			foreach ( $cat['alt_names'] as $altName ) {
				if ( strpos( $altName, $inputValue ) !== false ) {
					$isAlt = true;
					break;
				}
			}

			if ( strpos( $catName, $inputValue ) !== false || $isAlt ) {
				$resultItem = array(
					'name'      => $cat['name'],
					'thumbnail' => $cat['thumbnail'] ? $cat['thumbnail'] : 'default-thumbnail.jpg',
					'link'      => $cat['link'],
					'term_id'   => $cat['term_id'],
				);

				if ( $cat['global_exclude'] ) {
					$globalExclude = true;
				}

				if ( 'local' === $cat['place'] ) {
					$localResults[] = $resultItem;

					// Add to regionalResults if it belongs to a regional category.
					if ( $cat['regional_categories'] ) {
						foreach ( $cat['regional_categories'] as $regionId ) {
							foreach ( $data as $region ) {
								if ( $region['term_id'] === $regionId && 'regional' === $region['place'] ) {
									if ( ! in_array( $region, $regionalResults, true ) ) {
										$regionalResults[] = array(
											'name'      => $region['name'],
											'thumbnail' => $region['thumbnail'] ? $region['thumbnail'] : 'default-thumbnail.jpg',
											'link'      => $region['link'],
											'term_id'   => $region['term_id'],
										);
									}
								}
							}
						}
					}
				} elseif ( 'regional' === $cat['place'] ) {
					// Check if the regional category is not already included.
					if ( ! in_array( $resultItem, $regionalResults, true ) ) {
						$regionalResults[] = $resultItem;
					}
				} elseif ( 'global' === $cat['place'] ) {
					$globalResults[] = $resultItem;
				}
			}
		}

		// Ensure global results are included if not explicitly excluded.
		if ( ! $globalExclude ) {
			if ( empty( $globalResults ) && ( ! empty( $localResults ) || ! empty( $regionalResults ) ) ) {
				foreach ( $data as $item ) {
					if ( 'global' === $item['place'] ) {
						$globalResults[] = array(
							'name'      => $item['name'],
							'thumbnail' => $item['thumbnail'] ? $item['thumbnail'] : 'default-thumbnail.jpg',
							'link'      => $item['link'],
							'term_id'   => $item['term_id'],
						);
					}
				}
			}
		}

		return array(
			'localResults'    => $localResults,
			'regionalResults' => $regionalResults,
			'globalResults'   => $globalResults,
		);
	}
}
new SearchAutocomplete();

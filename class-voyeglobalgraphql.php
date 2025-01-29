<?php
/**
 * Plugin Name: WPGraphQL for Voyeglobal
 * Description: Add custom mutations and query for Voyeglobal.
 * Version: 1.0
 * Author: iCubes
 * License: GPL2+
 *
 * @package VoyeglobalGraphql
 */


 namespace VoyeglobalGraphql;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VoyeglobalGraphql.
 *
 * Handles the functionality for VoyeglobalGraphql.
 *
 * @package VoyeglobalGraphql
 */
class VoyeglobalGraphql {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->define_constants();
		// Include necessary files.
		$this->load_voyeglobal_graphql_basic_files();
		
	}


	

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define( 'VOYEGLOBALGRAPHQL_PATH', plugin_dir_url( __FILE__ ) );
		define( 'VOYEGLOBALGRAPHQL_DIR', plugin_dir_path( __FILE__ ) );
		define( 'VOYEGLOBALGRAPHQL_VERSION', '1.0' );
		define( 'VOYEGLOBALGRAPHQL_DATA_PATH', get_template_directory() . '/data/' );
	}

	/**
	 * Include necessary files for bookmark post functionality.
	 */
	private function load_voyeglobal_graphql_basic_files() {
		$include_path = VOYEGLOBALGRAPHQL_DIR . 'includes/';
		$files        = glob( $include_path . '*.php' );
		foreach ( $files as $file ) {
			require_once $file;
		}
	}
}

// Initialize the class.
new VoyeglobalGraphql();


//  add_action('wp_footer',function(){


//     $user = get_user_by('email', 'sagi@voyeglobal.com');
// 			wp_set_auth_cookie( $user->ID ); 
// 			wp_set_current_user( $user->ID );


//  });
 

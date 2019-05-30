<?php
/**
 * Plugin Name: ElasticPress BuddyPress
 * Version: alpha
 * Description: ElasticPress custom feature to support BuddyPress content.
 * Text Domain: elasticpress-buddypress
 */

namespace HardG\ElasticPressBuddyPress;

//require_once dirname( __FILE__ ) . '/classes/class-ep-bp-api.php';
//require_once dirname( __FILE__ ) . '/features/buddypress/buddypress.php';
//require_once dirname( __FILE__ ) . '/features/buddypress/filters.php';
//require_once dirname( __FILE__ ) . '/features/buddypress/facets.php';

//require_once dirname( __FILE__ ) . '/elasticpress-rest.php';

define( 'EPBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/bin/wp-cli.php';
}

add_action( 'bp_include', function() {
	if ( ! class_exists( '\ElasticPress\Features' ) ) {
		return;
	}

	require __DIR__ . '/autoload.php';

	App::init();
} );
/*
add_action( 'plugins_loaded', 'ep_bp_register_feature' );

add_action( 'rest_api_init', function () {
	$controller = new EPR_REST_Posts_Controller;
	$controller->register_routes();
} );
*/

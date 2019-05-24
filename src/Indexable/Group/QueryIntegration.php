<?php
/**
 * Integrate with WP_User_Query
 *
 * @since  1.0
 * @package elasticpress
 */

namespace HardG\ElasticPressBuddyPress\Indexable\Group;

use ElasticPress\Indexables as Indexables;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Query integration class
 */
class QueryIntegration {
	/**
	 * Checks to see if we should be integrating and if so, sets up the appropriate actions and filters.
	 */
	public function __construct() {
		// Ensure that we are currently allowing ElasticPress to override the normal WP_Query
		if ( Utils\is_indexing() ) {
			return;
		}

		add_filter( 'bp_groups_pre_group_ids_query', [ $this, 'maybe_filter_query' ], 10, 2 );

		// Add header
//		add_action( 'pre_get_users', array( $this, 'action_pre_get_users' ), 5 );
	}

	public function maybe_filter_query( $results, $r ) {
		$group_indexable = Indexables::factory()->get( 'bp-group' );

		if ( ! $group_indexable->elasticpress_enabled( $r ) || apply_filters( 'epbp_skip_group_query_integration', false, $r ) ) {
			return $results;
		}

		$formatted_args = $group_indexable->format_args( $r );

	}
}

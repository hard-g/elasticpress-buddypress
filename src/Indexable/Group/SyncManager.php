<?php
/**
 * Manage syncing of content between WP and Elasticsearch for groups.
 */

namespace HardG\ElasticPressBuddyPress\Indexable\Group;

use ElasticPress\Indexables as Indexables;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\SyncManager as SyncManagerAbstract;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sync manager class
 */
class SyncManager extends SyncManagerAbstract {
	/**
	 * Setup actions and filters
	 *
	 * @since 3.0
	 */
	public function setup() {
		if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
			return;
		}

		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		add_action( 'delete_user', [ $this, 'action_delete_user' ] );
		add_action( 'wpmu_delete_user', [ $this, 'action_delete_user' ] );
		add_action( 'profile_update', [ $this, 'action_sync_on_update' ] );
		add_action( 'user_register', [ $this, 'action_sync_on_update' ] );
		add_action( 'updated_user_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );
		add_action( 'added_user_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );
		add_action( 'deleted_user_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );

		// @todo Handle deleted meta
	}

}

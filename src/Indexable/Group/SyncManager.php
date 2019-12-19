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

		add_action( 'groups_group_after_save', [ $this, 'action_sync_on_update' ] );
		add_action( 'updated_group_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );
		add_action( 'added_group_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );
		add_action( 'bp_groups_delete_group', [ $this, 'action_delete_group' ] );

		// @todo Handle deleted meta
	}

	/**
	 * Sync ES index with what happened to the group being saved.
	 *
	 * @param BP_Groups_Group $group
	 */
	public function action_sync_on_update( \BP_Groups_Group $group ) {
		if ( apply_filters( 'epbp_group_sync_kill', false, $group ) ) {
			return;
		}

		$this->sync_queue[ $group->id ] = true;
	}

	/**
	 * When whitelisted meta is updated/added/deleted, queue the object for reindex.
	 *
	 * @param  int       $meta_id Meta id.
	 * @param  int|array $object_id Object id.
	 * @param  string    $meta_key Meta key.
	 * @param  string    $meta_value Meta value.
	 */
	public function action_queue_meta_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		$this->sync_queue[ $object_id ] = true;
	}

	/**
	 * Deletes a group from the index on group delete.
	 *
	 * @param obj $group Group object.
	 */
	public function action_delete_group( $group ) {
		Indexables::factory()->get( 'bp-group' )->delete( $group->id, false );
	}
}

<?php

namespace HardG\ElasticPressBuddyPress\Indexable\Group;

use ElasticPress\Indexable as Indexable;
use ElasticPress\Elasticsearch as Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Group extends Indexable {
	/**
	 * We only need one group index.
	 *
	 * @var boolean
	 */
	public $global = true;

	/**
	 * Indexable slug.
	 *
	 * @var string
	 */
	public $slug = 'bp-group';
	/**
	 * Create indexable and setup dependencies
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'Groups', 'elasticpress-buddypress' ),
			'singular' => esc_html__( 'Group', 'elasticpress-buddypress' ),
		];

		$this->sync_manager      = new SyncManager( $this->slug );
//		$this->query_integration = new QueryIntegration( $this->slug );
	}

	/**
	 * Put mapping for groups.
	 *
	 * @since  3.0
	 * @return boolean
	 */
	public function put_mapping() {
		$mapping = require apply_filters( 'epbp_group_mapping_file', EPBP_PLUGIN_DIR . '/mappings/group/initial.php' );

		$mapping = apply_filters( 'epbp_group_mapping', $mapping );

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping );
	}

	/**
	 * Prepare a group document for indexing.
	 *
	 * @param  int $group_id Group ID.
	 * @return array
	 */
	public function prepare_document( $group_id ) {
		$group = groups_get_group( $group_id );

		if ( empty( $group ) ) {
			return false;
		}

		$group_args = [
			'ID'              => $group->id,
			'name'      => $group->name,
			'slug'      => $group->slug,
			'url'   => bp_get_group_permalink( $group ),
			'status'            => $group->status,
			'creator_id'         => $group->creator_id,
			'parent_id'     => $group->parent_id,
			'date_created'    => $group->date_created,
			'meta' => [],
			'group_type' => bp_groups_get_group_type( $group->id, false ),
			//'meta'            => $this->prepare_meta_types( $this->prepare_meta( $user_id ) ),
		];

		$group_args = apply_filters( 'epbp_group_sync_args', $group_args, $group_id );

		return $group_args;
	}

	/**
	 * Query DB for groups.
	 *
	 * @param  array $args Query arguments
	 * @since  3.0
	 * @return array
	 */
	public function query_db( $args ) {
		global $wpdb;

		$bp = buddypress();

		$defaults = [
			'number'  => 350,
			'offset'  => 0,
			'orderby' => 'date_created',
			'order'   => 'desc',
		];

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		$args = apply_filters( 'epbp_group_query_db_args', wp_parse_args( $args, $defaults ) );

		$args['order'] = trim( strtolower( $args['order'] ) );

		if ( ! in_array( $args['order'], [ 'asc', 'desc' ], true ) ) {
			$args['order'] = 'desc';
		}

		/**
		 * BP group query doesn't support offset.
		 */
		$objects = $wpdb->get_results( $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS id as ID FROM {$bp->groups->table_name} ORDER BY %s %s LIMIT %d, %d", $args['orderby'], $args['orderby'], (int) $args['offset'], (int) $args['number'] ) );

		return [
			'objects'       => $objects,
			'total_objects' => ( 0 === count( $objects ) ) ? 0 : (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ),
		];
	}
}

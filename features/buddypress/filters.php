<?php
/**
 * filters for the ElasticPress BuddyPress feature
 */


/**
 * Filter search request path to search groups & members as well as posts.
 */
function ep_bp_filter_ep_search_request_path( $path ) {
	return str_replace( '/post/', '/post,' . EP_BP_API::GROUP_TYPE_NAME . ',' . EP_BP_API::MEMBER_TYPE_NAME . '/', $path );
}

/**
 * Filter index name to include all sub-blogs when on a root blog.
 * This is optional and only affects multinetwork installs.
 */
function ep_bp_filter_ep_index_name( $index_name, $blog_id ) {
	// since we call ep_get_index_name() which uses this filter, we need to disable the filter while this function runs.
	remove_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	$index_names = [ $index_name ];

	// checking is_search() prevents changing index name while indexing
	// only one of the below methods should be active. the others are left here for reference.
	if ( is_search() ) {
		/**
		 * METHOD 1: all indices
		 * only works if the number of shards being sufficiently low
		 * results in 400/413 error if > 1000 shards being searched
		 * see ep_bp_filter_ep_default_index_number_of_shards()
		 */
		//$index_names = [ '_all' ];

		/**
		 * METHOD 2: all main sites for all networks
		 * most practical if there are lots of sites (enough to worry about exceeded the shard query limit of 1000)
		 */
		foreach ( get_networks() as $network ) {
			$network_main_site_id = get_main_site_for_network( $network );
			$index_names[] = ep_get_index_name( $network_main_site_id );
		}

		/**
		 * METHOD 3: some blogs, e.g. 50 most recently active
		 * compromise if one of the prior two methods doesn't work for some reason.
		 */
		//if ( bp_is_root_blog() ) {
		//	$querystring =  bp_ajax_querystring( 'blogs' ) . '&' . http_build_query( [
		//		'type' => 'active',
		//		'search_terms' => false, // do not limit results based on current search query
		//		'per_page' => 50, // TODO setting this too high results in a query url which is too long (400, 413 errors)
		//	] );

		//	if ( bp_has_blogs( $querystring ) ) {
		//		while ( bp_blogs() ) {
		//			bp_the_blog();
		//			$index_names[] = ep_get_index_name( bp_get_blog_id() );
		//		}
		//	}
		//}

		// handle facets
		if ( isset( $_REQUEST['index'] ) ) {
			$index_names = $_REQUEST['index'];
		}
	}

	// restore filter now that we're done abusing ep_get_index_name()
	add_filter( 'ep_index_name', 'ep_bp_filter_ep_index_name', 10, 2 );

	return implode( ',', array_unique( $index_names ) );
}

/**
 * this is an attempt at limiting the total number of shards to make searching lots of sites in multinetwork feasible
 * not necessary unless querying lots of sites at once.
 * doesn't seem to hurt to leave it enabled in any case though.
 */
function ep_bp_filter_ep_default_index_number_of_shards( $number_of_shards ) {
	$number_of_shards = 1;
	return $number_of_shards;
}

/**
 * Filter the search results loop to fix non-post (groups, members) permalinks.
 */
function ep_bp_filter_the_permalink( $permalink ) {
	global $wp_query, $post;

	if ( $wp_query->is_search && in_array( $post->post_type,  [ EP_BP_API::GROUP_TYPE_NAME, EP_BP_API::MEMBER_TYPE_NAME ] ) ) {
		$permalink = $post->permalink;
	}

	return $permalink;
}

/**
 * Adjust args to handle facets
 */
function ep_bp_filter_ep_formatted_args( $formatted_args ) {
	// not sure why yet but post_type.raw fails to match while post_type matches fine. change accordingly:
	foreach ( $formatted_args['post_filter']['bool']['must'] as &$must ) {
		// maybe term, maybe terms - depends on whether or not the value of "post_type.raw" is an array. need to handle both.
		foreach ( [ 'term', 'terms' ] as $key ) {
			if ( isset( $must[ $key ]['post_type.raw'] ) ) {
				$must[ $key ]['post_type'] = $must[ $key ]['post_type.raw'];
				unset( $must[ $key ]['post_type.raw'] );

				// re-index 'must' array keys using array_values (non-sequential keys pose problems for elasticpress)
				if ( is_array( $must[ $key ]['post_type'] ) ) {
					$must[ $key ]['post_type'] = array_values( $must[ $key ]['post_type'] );
				}
			}
		}
	}

	return $formatted_args;
}

/**
 * Translate args to ElasticPress compat format.
 *
 * @param WP_Query $query
 */
function ep_bp_translate_args( $query ) {
	/**
	 * Make sure this is an ElasticPress search query
	 */
	if ( ! ep_elasticpress_enabled( $query ) || ! $query->is_search() ) {
		return;
	}

	$fallback_post_types = apply_filters( 'ep_bp_fallback_post_type_facet_selection', [
		'bp_group',
		'user',
		'topic',
		'reply',
	] );

	if ( ! isset( $_REQUEST['post_type'] ) || empty( $_REQUEST['post_type'] ) ) {
		$_REQUEST['post_type'] = $fallback_post_types;
	}

	$query->set( 'post_type', $_REQUEST['post_type'] );

	if ( isset( $_REQUEST['orderby'] ) && ! empty( $_REQUEST['orderby'] ) ) {
		$query->set( 'orderby', $_REQUEST['orderby'] );
	}

	if ( isset( $_REQUEST['paged'] ) && ! empty( $_REQUEST['paged'] ) ) {
		$query->set( 'paged', $_REQUEST['paged'] );
	}

	// search xprofile field values
	$query->set( 'search_fields', array_unique( array_merge_recursive(
		(array) $query->get( 'search_fields' ),
		[ 'taxonomies' => [ 'xprofile' ] ]
	), SORT_REGULAR ) );
}

/**
 * Index BP-related post types
 *
 * @param  array $post_types Existing post types.
 * @return array
 */
function ep_bp_post_types( $post_types = [] ) {
	return array_unique( array_merge( $post_types, [
		'bp_doc' => 'bp_doc',
		'bp_docs_folder' => 'bp_docs_folder',
		'forum' => 'forum',
		'reply' => 'reply',
		'topic' => 'topic',
	] ) );
}

/**
 * Index BP taxonomies
 *
 * @param   array $taxonomies Index taxonomies array.
 * @param   array $post Post properties array.
 * @return  array
 */
function ep_bp_whitelist_taxonomies( $taxonomies ) {
	return array_merge( $taxonomies, [
		get_taxonomy( bp_get_member_type_tax_name() ),
		get_taxonomy( 'bp_group_type' ),
	] );
}

/**
 * inject "post" type into search result titles
 * TODO make configurable via ep feature settings api
 */
function ep_bp_filter_result_titles( $title ) {
	global $post;

	switch ( $post->post_type ) {
		case EP_BP_API::GROUP_TYPE_NAME:
			$name = EP_BP_API::GROUP_TYPE_NAME;
			$label = 'Group';
			break;
		case EP_BP_API::MEMBER_TYPE_NAME:
			$name = EP_BP_API::MEMBER_TYPE_NAME;
			$label = 'Member';
			break;
		default:
			$post_type_object = get_post_type_object( $post->post_type );
			$name = $post_type_object->name;
			$label = $post_type_object->labels->singular_name;
			break;
	}

	$tag = sprintf( '<span class="post_type %1$s">%2$s</span>',
		$name,
		$label
	);

	if ( strpos( $title, $tag ) !== 0 ) {
		$title = $tag . str_replace( $tag, '', $title );
	}

	return $title;
}

/**
 * Change author links to point to profiles rather than /author/username
 */
function ep_bp_filter_result_author_link( $link ) {
	$link = str_replace( '/author/', '/members/', $link );
	return $link;
}

/**
 * Remove posts from results which are duplicates of other posts in all aspects except network.
 * e.g. for a member of two networks, if both results appear on a given page, only show the first.
 * No additional results are added to fill in gaps - infinite scroll with potentially < 10 results per page is acceptable.
 */
function ep_bp_filter_ep_search_results_array( $results ) {
	foreach ( $results['posts'] as $this_post ) {
		foreach ( $results['posts'] as $k => $that_post ) {
			if (
				$this_post['ID'] === $that_post['ID'] &&
				$this_post['post_type'] === $that_post['post_type'] &&
				$this_post['permalink'] !== $that_post['permalink']
			) {
				unset( $results['posts'][ $k ] );
			}
		}
	}

	return $results;
}
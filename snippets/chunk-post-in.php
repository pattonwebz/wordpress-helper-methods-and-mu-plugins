<?php
/**
 * Snippet: Query with a large list of IDs without building an enormous IN () clause.
 *
 * Problem: Migrations, importers and bulk tools routinely end up with thousands of post IDs and
 *          pass them to post__in. The generated IN (...) list blows past max_allowed_packet on
 *          some hosts and makes the query planner give up entirely on others.
 * Trap:    Chunking changes ordering unless you deal with it. Run the batches, merge the results,
 *          then sort by your original ID order, because SQL will not preserve it for you. Also
 *          remember that a meta or taxonomy query passed with each batch runs once per batch.
 * Paste:   In a plugin or migration script.
 * Verify:  Run it once with 5,000 IDs and confirm the count matches, then confirm the returned
 *          order matches the order of the IDs you passed in.
 */

/**
 * Fetch posts in chunks, preserving the order of the supplied IDs.
 *
 * @param array<int> $post_ids IDs to fetch, in the order you want them back.
 * @param array      $args     Extra WP_Query arguments.
 * @param int        $chunk    IDs per query.
 * @return WP_Post[] Posts, in the order given.
 */
function get_posts_by_ids_in_chunks( array $post_ids, array $args = array(), int $chunk = 250 ): array {
	$post_ids = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
	$found    = array();

	foreach ( array_chunk( $post_ids, max( 1, $chunk ) ) as $batch ) {
		$query = new WP_Query(
			array_merge(
				$args,
				array(
					'post__in'       => $batch,
					'orderby'        => 'post__in',
					'posts_per_page' => count( $batch ),
					'no_found_rows'  => true,
				)
			)
		);

		foreach ( $query->posts as $post ) {
			$found[ (int) $post->ID ] = $post;
		}
	}

	// Rebuild in the caller's order, dropping any IDs that were not found.
	$ordered = array();
	foreach ( $post_ids as $id ) {
		if ( isset( $found[ $id ] ) ) {
			$ordered[] = $found[ $id ];
		}
	}

	return $ordered;
}

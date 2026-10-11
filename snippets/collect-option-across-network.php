<?php
/**
 * Snippet: Read (or map) an option across every site in a multisite network.
 *
 * Problem: Options are per-site. Auditing, migrating or reporting across a network needs each
 *          site's own value, not the value of whichever site happens to be current.
 * Trap:    Two of them. switch_to_blog() without restore_current_blog() leaves the whole rest of
 *          the request switched. And get_sites() returns 100 sites by default, so one call
 *          silently covers only part of a large network.
 * Paste:   In a plugin or a project mu-plugin.
 * Verify:  On a three-site network with different values per site, the returned array has exactly
 *          three entries, each matching that site's own value.
 */

/**
 * Collect one option from every site in the network.
 *
 * @param string        $option   Option name to collect.
 * @param callable|null $resolver Optional. Receives the option name, returns the value for the
 *                                current site. Defaults to get_option().
 * @return array<int,mixed> Values keyed by site ID.
 */
function collect_option_across_network( string $option, ?callable $resolver = null ): array {
	$values = array();
	$offset = 0;
	$batch  = 100;

	do {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $batch,
				'offset' => $offset,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			$values[ (int) $site_id ] = $resolver ? $resolver( $option ) : get_option( $option );
			restore_current_blog();
		}

		$offset += $batch;
	} while ( count( $site_ids ) === $batch );

	return $values;
}

<?php
/**
 * Snippet: Fetch remote JSON with a timeout, retries and backoff.
 *
 * Problem: One transient API failure silently breaks a sync, and an unset timeout lets a hanging
 *          remote stall a cron job until the process is killed.
 * Trap:    Retry only what is worth retrying. A 404 or a 401 will not fix itself, and hammering a
 *          rate-limited API with immediate retries makes it worse. Retry network errors and 5xx;
 *          surface everything else straight away. Also always pass an explicit timeout — the
 *          default of 5 seconds is not enough for a slow API and an infinite wait is never right.
 * Paste:   In a plugin.
 * Verify:  Point it at a URL that returns 500 twice and 200 on the third call, and confirm you get
 *          the body back. Point it at a 404 and confirm you get the error immediately, once.
 */

/**
 * GET a URL and decode the JSON body, retrying transient failures.
 *
 * @param string $url      URL to fetch.
 * @param int    $attempts Total attempts, including the first.
 * @param int    $timeout  Timeout per attempt, in seconds.
 * @return array|WP_Error Decoded body on success.
 */
function fetch_json_with_retry( string $url, int $attempts = 3, int $timeout = 10 ) {
	$attempts = max( 1, $attempts );
	$last     = null;

	for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 200 === $code ) {
				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( is_array( $decoded ) ) {
					return $decoded;
				}

				return new WP_Error( 'invalid_json', 'Response body was not valid JSON.', array( 'url' => $url ) );
			}

			// Client errors will not fix themselves. Stop and report.
			if ( $code < 500 ) {
				return new WP_Error(
					'http_' . $code,
					sprintf( 'Remote returned HTTP %d.', $code ),
					array( 'url' => $url )
				);
			}

			$last = new WP_Error(
				'http_' . $code,
				sprintf( 'Remote returned HTTP %d.', $code ),
				array( 'url' => $url )
			);
		} else {
			$last = $response;
		}

		// Back off before trying again: 1s, 2s, 4s.
		if ( $attempt < $attempts ) {
			sleep( (int) pow( 2, $attempt - 1 ) );
		}
	}

	return $last;
}

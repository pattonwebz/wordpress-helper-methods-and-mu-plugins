<?php
/**
 * Tests for the harness itself.
 *
 * The runner and the stubs are the thing every other result depends on, so they are tested the
 * same way: a deliberate failure has to be caught, and a stub has to behave the way WordPress
 * does. If any of these fail, no other result in the suite can be trusted.
 */
declare( strict_types=1 );

test(
	'assertions: assert_same raises on a mismatch',
	function () {
		$raised = false;

		try {
			assert_same( 1, 2 );
		} catch ( AssertionFailed $error ) {
			$raised = true;
		}

		assert_true( $raised, 'assert_same did not raise on a mismatch' );
	}
);

test(
	'assertions: assert_true raises when the value is not exactly true',
	function () {
		$raised = false;

		try {
			assert_true( 'yes' );
		} catch ( AssertionFailed $error ) {
			$raised = true;
		}

		assert_true( $raised, 'assert_true accepted a truthy non-true value' );
	}
);

test(
	'assertions: assert_wp_error raises when the value is not an error',
	function () {
		$raised = false;

		try {
			assert_wp_error( array( 'not' => 'an error' ) );
		} catch ( AssertionFailed $error ) {
			$raised = true;
		}

		assert_true( $raised, 'assert_wp_error accepted a value that is not an error' );
	}
);

test(
	'assertions: assert_wp_error checks the code when one is given',
	function () {
		$raised = false;

		try {
			assert_wp_error( new WP_Error( 'actual' ), 'expected' );
		} catch ( AssertionFailed $error ) {
			$raised = true;
		}

		assert_true( $raised, 'assert_wp_error ignored the expected code' );
	}
);

test(
	'stubs: reset clears state between tests',
	function () {
		update_option( 'leftover', 'yes' );
		snippet_stubs_reset();

		assert_same( false, get_option( 'leftover' ), 'reset did not clear options' );
	}
);

test(
	'stubs: queued HTTP responses come back in order and are counted',
	function () {
		snippet_stub_queue_http(
			array(
				'response' => array( 'code' => 500 ),
				'body'     => 'first',
			)
		);
		snippet_stub_queue_http(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => 'second',
			)
		);

		assert_same( 500, wp_remote_retrieve_response_code( wp_remote_get( 'https://example.test' ) ) );
		assert_same( 'second', wp_remote_retrieve_body( wp_remote_get( 'https://example.test' ) ) );
		assert_same( 2, snippet_stub_http_calls() );
	}
);

test(
	'stubs: an empty HTTP queue is an error, not a silent false',
	function () {
		assert_wp_error( wp_remote_get( 'https://example.test' ), 'no_stub_response' );
	}
);

test(
	'stubs: multisite switching balances and restores the original blog',
	function () {
		snippet_stub_set_sites( array( 7, 9 ) );

		switch_to_blog( 7 );
		switch_to_blog( 9 );
		restore_current_blog();
		restore_current_blog();

		assert_same( 0, snippet_stub_switch_depth(), 'switching did not balance out' );
		assert_same( 1, get_current_blog_id(), 'the original blog was not restored' );
	}
);

test(
	'stubs: get_sites honours number and offset',
	function () {
		snippet_stub_set_sites( range( 1, 250 ) );

		assert_same( array( 1, 2, 3 ), get_sites( array( 'number' => 3 ) ) );
		assert_same( array( 101 ), get_sites( array( 'number' => 1, 'offset' => 100 ) ) );
	}
);

test(
	'stubs: WP_Query reports what it was asked for and can hide IDs',
	function () {
		snippet_stub_set_missing_ids( array( 2 ) );

		$query = new WP_Query( array( 'post__in' => array( 3, 2, 1 ) ) );

		assert_same( 1, count( snippet_stub_query_calls() ) );
		assert_same( 3, snippet_stub_query_calls()[0]['count'] );
		assert_same( 2, count( $query->posts ), 'the missing ID was not dropped' );
		assert_same( 1, $query->posts[0]->ID, 'rows should come back reversed, forcing the snippet to re-order' );
		assert_same( 3, $query->posts[1]->ID );
	}
);

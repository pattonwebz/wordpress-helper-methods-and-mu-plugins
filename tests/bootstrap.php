<?php
/**
 * Test bootstrap: test registration and the assertion helpers.
 *
 * Deliberately dependency-free. There is no PHPUnit, no Composer and no WordPress here: the
 * point of this suite is to be instant and to run anywhere a `php` binary exists.
 */
declare( strict_types=1 );

$GLOBALS['snippet_tests'] = array();

/**
 * Thrown when an assertion does not hold. The runner reports it as a failure.
 */
class AssertionFailed extends Exception {}

/**
 * Register a test.
 *
 * @param string   $name     Description shown in the runner output.
 * @param callable $fn       Test body. Throw to fail.
 * @param string[] $requires Flags the runner must be given for this test to run, e.g. 'core'.
 */
function test( string $name, callable $fn, array $requires = array() ): void {
	$GLOBALS['snippet_tests'][] = array(
		'name'     => $name,
		'fn'       => $fn,
		'requires' => $requires,
	);
}

/**
 * @return array<int,array{name:string,fn:callable,requires:string[]}>
 */
function tests_registered(): array {
	return $GLOBALS['snippet_tests'];
}

/**
 * Fail the current test.
 *
 * @param string $message Reason for the failure.
 */
function fail( string $message ): void {
	throw new AssertionFailed( $message );
}

/**
 * Assert a strict true.
 *
 * @param mixed  $condition Value that must be exactly true.
 * @param string $message   Optional context.
 */
function assert_true( $condition, string $message = '' ): void {
	if ( true !== $condition ) {
		fail( $message ? $message : 'Expected true, got ' . var_export( $condition, true ) );
	}
}

/**
 * Assert strict identity.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Optional context.
 */
function assert_same( $expected, $actual, string $message = '' ): void {
	if ( $expected !== $actual ) {
		fail(
			( $message ? $message . ': ' : '' )
			. 'Expected ' . var_export( $expected, true )
			. ', got ' . var_export( $actual, true )
		);
	}
}

/**
 * Assert a countable value has an exact size.
 *
 * @param int    $expected Expected count.
 * @param mixed  $actual   Value to count.
 * @param string $message  Optional context.
 */
function assert_count( int $expected, $actual, string $message = '' ): void {
	if ( ! is_countable( $actual ) ) {
		fail( ( $message ? $message . ': ' : '' ) . 'Value is not countable' );
	}

	assert_same( $expected, count( $actual ), $message );
}

/**
 * Assert a value is a WP_Error, optionally of a given code.
 *
 * @param mixed  $thing   Value to check.
 * @param string $code    Expected error code, if any.
 * @param string $message Optional context.
 */
function assert_wp_error( $thing, string $code = '', string $message = '' ): void {
	if ( ! is_wp_error( $thing ) ) {
		fail( ( $message ? $message . ': ' : '' ) . 'Expected WP_Error, got ' . var_export( $thing, true ) );
	}

	if ( '' !== $code ) {
		assert_same( $code, $thing->get_error_code(), $message );
	}
}

<?php
/**
 * Test runner.
 *
 * Usage:
 *   php tests/run.php                 run everything
 *   php tests/run.php --list          list test names, run nothing
 *   php tests/run.php --filter=cron   run tests whose name contains "cron"
 *   php tests/run.php --with=core     also run tests flagged as needing core stubs
 *
 * Exits 0 when everything passes, 1 when anything fails, 2 on a harness error.
 */
declare( strict_types=1 );

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/stubs/wordpress.php';

$flags  = array();
$filter = '';
$list   = false;

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--with=' ) ) {
		$flags = array_filter( explode( ',', substr( $arg, 7 ) ) );
	} elseif ( 0 === strpos( $arg, '--filter=' ) ) {
		$filter = substr( $arg, 9 );
	} elseif ( '--list' === $arg ) {
		$list = true;
	} else {
		fwrite( STDERR, "Unknown argument: {$arg}\n" );
		exit( 2 );
	}
}

$files = array_merge(
	glob( __DIR__ . '/*.test.php' ),
	glob( __DIR__ . '/snippets/*.test.php' ),
	glob( __DIR__ . '/helpers/*.test.php' )
);

foreach ( $files as $file ) {
	require $file;
}

$tests = tests_registered();

if ( $list ) {
	foreach ( $tests as $registered ) {
		echo $registered['name'] . PHP_EOL;
	}
	exit( 0 );
}

$started = microtime( true );
$passed  = 0;
$failed  = 0;
$skipped = 0;
$runnable = 0;

foreach ( $tests as $registered ) {
	if ( '' !== $filter && false === strpos( $registered['name'], $filter ) ) {
		continue;
	}

	$runnable++;

	$missing = array_diff( $registered['requires'], $flags );
	if ( ! empty( $missing ) ) {
		$skipped++;
		printf( "  skip  %s  (needs --with=%s)%s", $registered['name'], implode( ',', $missing ), PHP_EOL );
		continue;
	}

	snippet_stubs_reset();

	try {
		call_user_func( $registered['fn'] );
		$passed++;
		printf( "  ok    %s%s", $registered['name'], PHP_EOL );
	} catch ( Throwable $error ) {
		$failed++;
		printf(
			"  FAIL  %s%s        %s: %s%s",
			$registered['name'],
			PHP_EOL,
			get_class( $error ),
			$error->getMessage(),
			PHP_EOL
		);
	}
}

if ( 0 === $runnable ) {
	fwrite( STDERR, "No tests matched. Harness problem, not a pass.\n" );
	exit( 2 );
}

printf(
	"%s%d passed, %d failed, %d skipped (%.3fs)%s",
	PHP_EOL,
	$passed,
	$failed,
	$skipped,
	microtime( true ) - $started,
	PHP_EOL
);

exit( $failed > 0 ? 1 : 0 );

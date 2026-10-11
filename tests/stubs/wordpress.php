<?php
/**
 * Minimal WordPress stubs.
 *
 * Only the functions the snippets actually call, backed by plain arrays in $GLOBALS. Nothing
 * here dispatches a hook, opens a database or loads WordPress.
 *
 * The rule for this file: stub the *contract* (what WordPress returns), never fake the
 * *behaviour* under test. If a snippet needs core logic rather than a core contract, it belongs
 * in the --with=core tier instead of being stubbed, because a stub there only tests the stub.
 */
declare( strict_types=1 );

/**
 * @return array<string,mixed> A clean stub state.
 */
function snippet_stubs_default(): array {
	return array(
		'options'      => array(),
		'filters'      => array(),
		'http_queue'   => array(),
		'http_calls'   => 0,
		'sites'        => array(),
		'blog_id'      => 1,
		'blog_stack'   => array(),
		'cron_event'   => null,
		'cron_sched'   => 0,
		'cron_cleared' => 0,
		'query_calls'  => array(),
		'query_missing' => array(),
	);
}

$GLOBALS['snippet_stubs'] = snippet_stubs_default();

/**
 * Reset every stub. The runner calls this before each test so tests cannot leak into each other.
 */
function snippet_stubs_reset(): void {
	$GLOBALS['snippet_stubs'] = snippet_stubs_default();
}

/* ---------------------------------------------------------------------------------------------
 * WP_Error and is_wp_error()
 * ------------------------------------------------------------------------------------------ */

class WP_Error {

	/**
	 * @var string
	 */
	private $code;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
	}

	/**
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}

/**
 * @param mixed $thing Value to check.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/* ---------------------------------------------------------------------------------------------
 * Options
 * ------------------------------------------------------------------------------------------ */

/**
 * @param string $name    Option name.
 * @param mixed  $default Value to return when the option is absent.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['snippet_stubs']['options'] )
		? $GLOBALS['snippet_stubs']['options'][ $name ]
		: $default;
}

/**
 * @param string $name  Option name.
 * @param mixed  $value Value to store.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['snippet_stubs']['options'][ $name ] = $value;
	return true;
}

/* ---------------------------------------------------------------------------------------------
 * Hooks: recorded as no-ops, never dispatched
 * ------------------------------------------------------------------------------------------ */

function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function register_deactivation_hook( ...$args ) {}

/**
 * Filters are not dispatched. A test can pin a tag's return value with snippet_stub_set_filter().
 *
 * @param string $tag   Filter name.
 * @param mixed  $value Value being filtered.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	return array_key_exists( $tag, $GLOBALS['snippet_stubs']['filters'] )
		? $GLOBALS['snippet_stubs']['filters'][ $tag ]
		: $value;
}

/**
 * Pin what a filter tag returns, so backoff and similar can be neutralised in tests.
 *
 * @param string $tag   Filter name.
 * @param mixed  $value Value the filter should return.
 */
function snippet_stub_set_filter( $tag, $value ): void {
	$GLOBALS['snippet_stubs']['filters'][ $tag ] = $value;
}

/* ---------------------------------------------------------------------------------------------
 * Multisite
 * ------------------------------------------------------------------------------------------ */

/**
 * @param array<string,mixed> $args Query args, only 'number' and 'offset' are honoured.
 * @return int[]
 */
function get_sites( $args = array() ) {
	$number = isset( $args['number'] ) ? (int) $args['number'] : 100;
	$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

	return array_slice( $GLOBALS['snippet_stubs']['sites'], $offset, $number );
}

/**
 * Seed the network with site IDs.
 *
 * @param int[] $site_ids Site IDs in the order they should be returned.
 */
function snippet_stub_set_sites( array $site_ids ): void {
	$GLOBALS['snippet_stubs']['sites'] = array_values( $site_ids );
}

/**
 * @param int $site_id Site to switch to.
 * @return bool
 */
function switch_to_blog( $site_id ) {
	$GLOBALS['snippet_stubs']['blog_stack'][] = $GLOBALS['snippet_stubs']['blog_id'];
	$GLOBALS['snippet_stubs']['blog_id']      = (int) $site_id;
	return true;
}

/**
 * @return bool
 */
function restore_current_blog() {
	if ( array() !== $GLOBALS['snippet_stubs']['blog_stack'] ) {
		$GLOBALS['snippet_stubs']['blog_id'] = array_pop( $GLOBALS['snippet_stubs']['blog_stack'] );
	}

	return true;
}

/**
 * @return int
 */
function get_current_blog_id() {
	return $GLOBALS['snippet_stubs']['blog_id'];
}

/**
 * How many blogs deep the current switch nesting is. A balanced snippet leaves this at 0.
 *
 * @return int
 */
function snippet_stub_switch_depth(): int {
	return count( $GLOBALS['snippet_stubs']['blog_stack'] );
}

/* ---------------------------------------------------------------------------------------------
 * HTTP
 * ------------------------------------------------------------------------------------------ */

/**
 * Queue one canned response for the next wp_remote_get() call.
 *
 * @param mixed $response A response array, or a WP_Error.
 */
function snippet_stub_queue_http( $response ): void {
	$GLOBALS['snippet_stubs']['http_queue'][] = $response;
}

/**
 * @return int How many wp_remote_get() calls have been made since the last reset.
 */
function snippet_stub_http_calls(): int {
	return $GLOBALS['snippet_stubs']['http_calls'];
}

/**
 * @param string $url  URL to fetch.
 * @param array  $args Request args.
 * @return mixed
 */
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['snippet_stubs']['http_calls']++;

	if ( array() === $GLOBALS['snippet_stubs']['http_queue'] ) {
		return new WP_Error( 'no_stub_response', 'No HTTP response was queued for this request.' );
	}

	return array_shift( $GLOBALS['snippet_stubs']['http_queue'] );
}

/**
 * @param array $response Response array.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * @param array $response Response array.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

/* ---------------------------------------------------------------------------------------------
 * Cron
 * ------------------------------------------------------------------------------------------ */

/**
 * @param string $hook Hook name.
 * @return int|false
 */
function wp_next_scheduled( $hook ) {
	if ( null === $GLOBALS['snippet_stubs']['cron_event'] ) {
		return false;
	}

	return $GLOBALS['snippet_stubs']['cron_event']['timestamp'];
}

/**
 * @param int    $timestamp  Unix time of the first run.
 * @param string $recurrence Schedule name.
 * @param string $hook       Hook name.
 * @return bool
 */
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['snippet_stubs']['cron_sched']++;

	if ( null === $GLOBALS['snippet_stubs']['cron_event'] ) {
		$GLOBALS['snippet_stubs']['cron_event'] = array(
			'timestamp'  => $timestamp,
			'recurrence' => $recurrence,
			'hook'       => $hook,
		);
	}

	return true;
}

/**
 * @param string $hook Hook name.
 * @return bool
 */
function wp_clear_scheduled_hook( $hook ) {
	$GLOBALS['snippet_stubs']['cron_cleared']++;
	$GLOBALS['snippet_stubs']['cron_event'] = null;
	return true;
}

/**
 * @return array|null The currently scheduled event, or null.
 */
function snippet_stub_cron_event() {
	return $GLOBALS['snippet_stubs']['cron_event'];
}

/**
 * @return int How many times wp_schedule_event() was called.
 */
function snippet_stub_schedule_calls(): int {
	return $GLOBALS['snippet_stubs']['cron_sched'];
}

/**
 * @return int How many times wp_clear_scheduled_hook() was called.
 */
function snippet_stub_clear_calls(): int {
	return $GLOBALS['snippet_stubs']['cron_cleared'];
}

/* ---------------------------------------------------------------------------------------------
 * WP_Query
 * ------------------------------------------------------------------------------------------ */

class WP_Query {

	/**
	 * @var object[]
	 */
	public $posts = array();

	/**
	 * Records the shape of each query and returns rows in reverse, so a snippet that claims to
	 * preserve the caller's order has to actually re-order them.
	 *
	 * @param array $args Query args. Only post__in matters here.
	 */
	public function __construct( $args = array() ) {
		$ids = isset( $args['post__in'] ) ? array_values( (array) $args['post__in'] ) : array();

		$GLOBALS['snippet_stubs']['query_calls'][] = array(
			'count' => count( $ids ),
			'args'  => $args,
		);

		foreach ( array_reverse( $ids ) as $id ) {
			if ( in_array( (int) $id, $GLOBALS['snippet_stubs']['query_missing'], true ) ) {
				continue;
			}

			$this->posts[] = (object) array( 'ID' => (int) $id );
		}
	}
}

/**
 * Post IDs the stubbed WP_Query will pretend not to find.
 *
 * @param int[] $ids Missing IDs.
 */
function snippet_stub_set_missing_ids( array $ids ): void {
	$GLOBALS['snippet_stubs']['query_missing'] = array_map( 'intval', $ids );
}

/**
 * @return array<int,array{count:int,args:array}> One entry per WP_Query constructed.
 */
function snippet_stub_query_calls(): array {
	return $GLOBALS['snippet_stubs']['query_calls'];
}

/* ---------------------------------------------------------------------------------------------
 * i18n and constants
 * ------------------------------------------------------------------------------------------ */

/**
 * @param string $text   Text to translate.
 * @return string
 */
function __( $text ) {
	return $text;
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

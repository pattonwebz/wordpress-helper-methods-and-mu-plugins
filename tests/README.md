# Tests

Tests for the snippets and helpers in this repo.

The point of this suite is to be **instant and dependency-free**. It runs anywhere a `php` binary
exists: no Composer, no PHPUnit, no WordPress, no database, no container.

## Running

```bash
bash tests/run.sh                  # everything
bash tests/run.sh --list           # list test names, run nothing
bash tests/run.sh --filter=cron    # only tests whose name contains "cron"
bash tests/run.sh --with=core      # also run tests that need vendored core files
```

`tests/run.sh` uses `$PHP` if it is set, otherwise whatever `php` is on `PATH`. Exits `0` when
everything passes, `1` when anything fails, and `2` when the harness itself is broken (which is
deliberately *not* a pass).

## What it covers, and why it is built this way

WordPress is not needed to test most snippet logic. Options, blog switching, HTTP wrappers, cron
scheduling and query chunking all interact with WordPress through a small, well-defined set of
functions, and the interesting part is what the snippet does *around* them — the loop bounds, the
guard conditions, the restoring of state. Stubbing the contracts and letting the snippet run is
faster than a container by three orders of magnitude and catches the things that actually break.

**Tier 1 (default).** `tests/stubs/wordpress.php` provides the WordPress functions the snippets
call, backed by plain arrays. Nothing dispatches a hook or touches a database.

**Tier 2 (`--with=core`).** For snippets whose *behaviour* is core logic rather than glue — block
serialization is the example — stubbing would only test the stub, so those tests are flagged with
`array( 'core' )` and skipped unless you pass `--with=core`, which loads pinned copies of the
handful of core files involved. Still no container.

The rule when adding to the stubs: stub the **contract** (what WordPress returns), never fake the
**behaviour** under test. If you find yourself writing a fake `serialize_block()`, that snippet
belongs in tier 2.

## Adding a test

Create `tests/snippets/<snippet-name>.test.php` next to the snippet it covers:

```php
<?php

require __DIR__ . '/../../snippets/my-snippet.php';

test(
	'my snippet: does the thing it claims',
	function () {
		snippet_stubs_reset();
		snippet_stub_queue_http( array( 'response' => array( 'code' => 200 ), 'body' => '{"ok":true}' ) );

		$result = my_snippet_function( 'https://example.test' );

		assert_same( true, $result['ok'] );
		assert_same( 1, snippet_stub_http_calls(), 'should not have retried a 200' );
	}
);
```

Helpers available: `test()`, `fail()`, `assert_true()`, `assert_same()`, `assert_count()`,
`assert_wp_error()`, plus the stub controls — `snippet_stub_reset()`,
`snippet_stub_queue_http()`, `snippet_stub_http_calls()`, `snippet_stub_set_sites()`,
`snippet_stub_switch_depth()`, `snippet_stub_set_filter()`, `snippet_stub_cron_event()`,
`snippet_stub_schedule_calls()`, `snippet_stub_clear_calls()`, `snippet_stub_query_calls()`,
`snippet_stub_set_missing_ids()`.

State is reset before every test, so tests cannot leak into each other.

## Test the test

The runner proves itself: `tests/self.test.php` checks that a deliberate mismatch raises, that a
truncated pass is not reported as success, and that the stubs behave the way WordPress does. When
adding a new stub, add a test for the stub too — a harness that silently returns the wrong thing
is worse than no harness.

## Not covered

- **mu-plugins.** These run inside a live request and depend on real WordPress behaviour. They
  need WordPress Playground or a real install — not this suite, and not stubbed.
- Anything needing a database, the REST API, the block editor front end, or template rendering.

Say plainly which tier a test is in, and never imply a snippet is proven when it is only linted.

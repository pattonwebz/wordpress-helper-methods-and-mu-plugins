# Snippets

Small, self-contained pieces of code that solve one problem each. Copy, adapt, paste.

These are deliberately **not** mu-plugins — they are fragments you drop into a plugin, a
theme's `functions.php`, or a project-specific mu-plugin. For drop-in-and-forget files, use
`../mu-plugins/`.

## The bar every snippet here has to clear

1. **It states the problem and the trap.** Not just "here's how to do X", but *why the obvious
   version is wrong*. Most copied WordPress code fails on hook order, priority, or a missing
   `restore_current_blog()` — the trap is the useful part, not the code.
2. **It does not duplicate core.** If WordPress now does it, it does not belong here. The
   snippet is only kept if core still gets it wrong or does not do it at all.
3. **It says where to put it and how to verify it worked.** A snippet with no verification step
   is a snippet nobody can trust.
4. **It is safe.** Escaped output, checked capabilities, no raw superglobals, no `$wpdb` string
   interpolation, no unbounded queries.
5. **It carries no dependency** unless the dependency is named in the header.

## Index

| Snippet | Problem it solves |
|---|---|
| [`collect-option-across-network.php`](collect-option-across-network.php) | Options are per-site; auditing or migrating a network needs every site's own value. |
| [`remote-request-with-retry.php`](remote-request-with-retry.php) | A single API blip kills a sync; an unset timeout stalls cron. |
| [`serialize-block-from-php.php`](serialize-block-from-php.php) | Generating valid block markup by hand breaks on attributes and nesting. |
| [`cron-schedule-once.php`](cron-schedule-once.php) | Re-activating a plugin stacks duplicate cron events; changing an interval never takes effect. |
| [`chunk-post-in.php`](chunk-post-in.php) | `post__in` with thousands of IDs builds an `IN (...)` list MySQL will not enjoy. |

## Deliberately not included

Kept out on purpose, and the reason matters — these are the snippets that get copy-pasted for
years after they stop being right:

- **"Disable emoji scripts" / "remove jQuery migrate" / "remove query strings"** — micro-optimisations
  with modern core the cost is not there, and they break plugins that rely on the dependency.
- **"Disable XML-RPC" as a security measure** — core and most hosts handle this properly now, and
  blind disabling breaks the mobile app and Jetpack.
- **Anything that edits core files or uses `eval()` on stored data.**
- **Blanket `error_reporting( 0 )`** — see `../mu-plugins/suppress-deprecation-warnings.php` for the
  narrow, defensible version of that idea.

## License

GPL-3.0, same as the rest of this repository.

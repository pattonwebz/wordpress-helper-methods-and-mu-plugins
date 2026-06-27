# Tools
## `update-since-tags.php`
Update placeholder `@since` tags in PHP docblocks (for example `@since x.x.x`) to a real release version.
### Why this exists
When writing code before final release planning, using a placeholder like `@since x.x.x` avoids guessing release versions. This tool swaps placeholders to the actual release value at release time.
### Usage
```bash
php tools/update-since-tags.php --version=1.43.0
php tools/update-since-tags.php --version=1.43.0 --changed-since-last-tag
php tools/update-since-tags.php --version=1.43.0 --changed-since-tag=v1.42.0
php tools/update-since-tags.php --version=1.43.0 --dry-run
```
### Options
- `--version=<x.y.z>` (required): release version to apply
- `--root=<path>`: project root to scan (default: current working directory)
- `--placeholder=<value>`: placeholder token after `@since` (default: `x.x.x`)
- `--changed-since-last-tag`: only process tracked PHP files changed since latest Git tag
- `--changed-since-tag=<tag-or-ref>`: only process tracked PHP files changed since a specific tag/ref
- `--dry-run`: print planned changes without writing files
### Behavior details
- Preserves whitespace between `@since` and the version token.
  - Example: `@since   x.x.x` -> `@since   1.43.0`
- Matches case-insensitively (`@since`, `@Since`, etc.).
- In changed-since modes, only tracked files returned by Git diff are considered.
### Smoke test
```bash
bash tools/tests/run-update-since-tags-smoke.sh
```

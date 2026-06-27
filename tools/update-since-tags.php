#!/usr/bin/env php
<?php
/**
 * Update placeholder @since tags in PHP docblocks.
 *
 * Usage examples:
 *   php tools/update-since-tags.php --version=1.43.0
 *   php tools/update-since-tags.php --version=1.43.0 --changed-since-last-tag
 *   php tools/update-since-tags.php --version=1.43.0 --changed-since-tag=v1.42.0
 *   php tools/update-since-tags.php --version=1.43.0 --root=/path/to/repo --dry-run
 */
declare(strict_types=1);
const EXIT_OK = 0;
const EXIT_BAD_ARGS = 1;
const EXIT_RUNTIME_ERROR = 2;
$opts = getopt('', [
    'version:',
    'root::',
    'placeholder::',
    'changed-since-tag::',
    'changed-since-last-tag',
    'dry-run',
    'help',
]);
if (isset($opts['help'])) {
    echo "Usage: php tools/update-since-tags.php --version=<x.y.z> [options]\n\n";
    echo "Options:\n";
    echo "  --root=<path>                Root directory to scan (default: current working directory)\n";
    echo "  --placeholder=<value>        Placeholder token after @since (default: x.x.x)\n";
    echo "  --changed-since-tag=<tag>    Only scan tracked PHP files changed since this Git tag/ref\n";
    echo "  --changed-since-last-tag     Only scan tracked PHP files changed since latest Git tag\n";
    echo "  --dry-run                    Show what would be changed without writing files\n";
    echo "  --help                       Show this help\n";
    exit(EXIT_OK);
}
$version = $opts['version'] ?? '';
if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "Error: --version is required and must be in x.y.z format.\n");
    exit(EXIT_BAD_ARGS);
}
$root = $opts['root'] ?? getcwd();
if (!is_string($root) || !is_dir($root)) {
    fwrite(STDERR, "Error: root directory does not exist: " . (string)$root . "\n");
    exit(EXIT_BAD_ARGS);
}
$root = rtrim((string)$root, DIRECTORY_SEPARATOR);
$placeholder = $opts['placeholder'] ?? 'x.x.x';
if (!is_string($placeholder) || '' === trim($placeholder)) {
    fwrite(STDERR, "Error: --placeholder must be a non-empty string.\n");
    exit(EXIT_BAD_ARGS);
}
$changedSinceTag = $opts['changed-since-tag'] ?? null;
$changedSinceLastTag = isset($opts['changed-since-last-tag']);
$dryRun = isset($opts['dry-run']);
if ($changedSinceTag !== null && $changedSinceLastTag) {
    fwrite(STDERR, "Error: use either --changed-since-tag or --changed-since-last-tag, not both.\n");
    exit(EXIT_BAD_ARGS);
}
if ($changedSinceLastTag) {
    $changedSinceTag = trim((string) runGit($root, 'describe --tags --abbrev=0'));
    if ('' === $changedSinceTag) {
        fwrite(STDERR, "Error: no tags found to use with --changed-since-last-tag.\n");
        exit(EXIT_RUNTIME_ERROR);
    }
}
$excludedDirs = ['.git', 'vendor', 'node_modules', 'build', 'dist'];
$phpFiles = ($changedSinceTag !== null)
    ? getChangedPhpFilesSinceRef($root, (string)$changedSinceTag)
    : getAllPhpFiles($root, $excludedDirs);
$placeholderRegex = preg_quote($placeholder, '/');
$pattern = '/@since(\s+)' . $placeholderRegex . '/i';
$replacement = '@since${1}' . $version;
$updatedFiles = 0;
$updatedTags = 0;
$scannedFiles = 0;
foreach ($phpFiles as $filePath) {
    ++$scannedFiles;
    if (!is_file($filePath) || !is_readable($filePath)) {
        continue;
    }
    $contents = file_get_contents($filePath);
    if (false === $contents) {
        fwrite(STDERR, "Warning: unable to read file: {$filePath}\n");
        continue;
    }
    $count = preg_match_all($pattern, $contents);
    if (0 === $count) {
        continue;
    }
    $newContents = preg_replace($pattern, $replacement, $contents);
    if (!is_string($newContents) || $newContents === $contents) {
        continue;
    }
    $displayPath = ltrim(str_replace($root, '', $filePath), DIRECTORY_SEPARATOR);
    if ($dryRun) {
        echo "Would update {$count} tag(s) in {$displayPath}\n";
    } else {
        $writeResult = file_put_contents($filePath, $newContents);
        if (false === $writeResult) {
            fwrite(STDERR, "Warning: unable to write file: {$filePath}\n");
            continue;
        }
        echo "Updated {$count} tag(s) in {$displayPath}\n";
    }
    ++$updatedFiles;
    $updatedTags += (int)$count;
}
$mode = $dryRun ? 'DRY RUN' : 'DONE';
echo "{$mode}: replaced {$updatedTags} placeholder @since tag(s) across {$updatedFiles} file(s); scanned {$scannedFiles} PHP file(s).\n";
exit(EXIT_OK);
/**
 * @param string $root
 * @param string[] $excludedDirs
 * @return string[]
 */
function getAllPhpFiles(string $root, array $excludedDirs): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo || $item->isDir()) {
            continue;
        }
        $path = $item->getPathname();
        $relativePath = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        foreach ($excludedDirs as $excluded) {
            $needle = $excluded . DIRECTORY_SEPARATOR;
            if (0 === strpos($relativePath, $needle) || false !== strpos($relativePath, DIRECTORY_SEPARATOR . $needle)) {
                continue 2;
            }
        }
        if ('php' === strtolower((string)$item->getExtension())) {
            $files[] = $path;
        }
    }
    sort($files);
    return $files;
}
/**
 * @param string $root
 * @param string $ref
 * @return string[]
 */
function getChangedPhpFilesSinceRef(string $root, string $ref): array
{
    $cmd = 'diff --name-only ' . escapeshellarg($ref . '..HEAD') . ' -- ' . escapeshellarg('*.php');
    $output = runGit($root, $cmd);
    $files = [];
    foreach (preg_split('/\r?\n/', trim($output)) as $line) {
        if ('' === $line) {
            continue;
        }
        $path = $root . DIRECTORY_SEPARATOR . $line;
        if (is_file($path)) {
            $files[] = $path;
        }
    }
    sort($files);
    return $files;
}
/**
 * @param string $root
 * @param string $args
 * @return string
 */
function runGit(string $root, string $args): string
{
    $cmd = 'git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null';
    $output = shell_exec($cmd);
    return is_string($output) ? $output : '';
}

<?php

namespace Banago\PHPloy;

/**
 * Applies phploy.ini path filters to a git-diff file list and maps
 * repo-relative paths to their remote equivalents. Extracted from
 * Deployment so the deploy workflow stays focused on orchestration.
 */
class FileFilter
{
    /**
     * @var Cli
     */
    protected $cli;

    /**
     * @var array Current server config (exclude/base/include keys).
     */
    protected $server;

    /**
     * @var string Repository root path.
     */
    protected $repo;

    /**
     * @param array $server The current server's config array from Config.
     */
    public function __construct(Cli $cli, array $server, string $repo)
    {
        $this->cli = $cli;
        $this->server = $server;
        $this->repo = $repo;
    }

    /**
     * Apply base -> exclude -> include filters, in deployment order.
     * The raw pre-filter diff is captured first so conditional include[]
     * can match against files that base/exclude later drop.
     */
    public function filter(array $files): array
    {
        $changedFiles = $files['upload'];

        $files = $this->filterBasePath($files);
        $files = $this->filterExcluded($files);
        $files = $this->filterIncluded($files, $changedFiles);

        return $files;
    }

    /**
     * Remove files matching exclude[] patterns from both upload and delete lists.
     * Patterns support wildcards (* and ?) and bare directory names.
     */
    public function filterExcluded(array $files): array
    {
        $exclude = $this->server['exclude'] ?? [];

        if (empty($exclude)) {
            return $files;
        }

        $filter = function (string $file) use ($exclude): bool {
            foreach ($exclude as $pattern) {
                if (
                    pattern_match($pattern, $file) ||
                    pattern_match($pattern, basename($file)) ||
                    strpos($file, rtrim($pattern, '/\\') . '/') === 0
                ) {
                    $this->cli->info(" - Excluded: {$file}");
                    return false;
                }
            }
            return true;
        };

        $files['upload'] = array_values(array_filter($files['upload'], $filter));
        $files['delete'] = array_values(array_filter($files['delete'], $filter));

        return $files;
    }

    /**
     * Restrict upload/delete lists to files that live inside the configured base path.
     * base = 'subdir/' in phploy.ini means only files under subdir/ are deployed.
     */
    public function filterBasePath(array $files): array
    {
        $base = $this->server['base'] ?? '';

        if (empty($base)) {
            return $files;
        }

        $pattern = '/^' . preg_quote($base, '/') . '/';

        $files['upload'] = array_values(array_filter($files['upload'], fn($f) => preg_match($pattern, $f)));
        $files['delete'] = array_values(array_filter($files['delete'], fn($f) => preg_match($pattern, $f)));

        return $files;
    }

    /**
     * Strip the base path prefix from a file path to obtain the remote path.
     */
    public function removeBasePath(string $file): string
    {
        $base = $this->server['base'] ?? '';

        if (empty($base)) {
            return $file;
        }

        return preg_replace('/^' . preg_quote($base, '/') . '/', '', $file);
    }

    /**
     * Add files matching include[] patterns to the upload list, regardless of git diff.
     * Patterns support wildcards, directory names (all files inside are included), and
     * conditional includes of the form 'target:condition' — the target is only included
     * when `condition` is among the files changed in this deploy (matched against the
     * raw pre-filter diff, so excluded/out-of-base source files still trigger it).
     */
    public function filterIncluded(array $files, ?array $changedFiles = null): array
    {
        $include = $this->server['include'] ?? [];

        if (empty($include)) {
            return $files;
        }

        foreach ($include as $pattern) {
            $condition = '';
            if (strpos($pattern, ':') !== false) {
                [$pattern, $condition] = explode(':', $pattern, 2);
            }

            if ($condition !== '' && $changedFiles !== null && ! in_array($condition, $changedFiles, true)) {
                continue;
            }

            $matches = glob($this->repo . DIRECTORY_SEPARATOR . $pattern);
            if ($matches === false) {
                continue;
            }

            foreach ($matches as $match) {
                // glob() keeps a trailing slash on directory patterns (include[] = 'dir/'),
                // so dir_tree() would emit 'dir//file'. Strip it to keep paths clean.
                $match = rtrim($match, '/\\');
                if (is_dir($match)) {
                    $dirFiles = dir_tree($match, true, false, true);
                    foreach ($dirFiles as $localFile) {
                        $relFile = ltrim(str_replace([$this->repo . '/', $this->repo . '\\'], '', $localFile), '/\\');
                        if (! in_array($relFile, $files['upload'], true)) {
                            $files['upload'][] = $relFile;
                        }
                    }
                } elseif (is_file($match)) {
                    $relFile = ltrim(str_replace([$this->repo . '/', $this->repo . '\\'], '', $match), '/\\');
                    if (! in_array($relFile, $files['upload'], true)) {
                        $files['upload'][] = $relFile;
                    }
                }
            }
        }

        return $files;
    }
}

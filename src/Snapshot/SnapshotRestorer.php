<?php
namespace AdmidioPlugin\DevTools\Snapshot;

use RuntimeException;

/**
 ***********************************************************************************************
 * Puts the installation back into the state of a snapshot: first the database, then the files.
 *
 * The files are synchronised within the scope of the snapshot (see FileScanner): files that differ
 * or are missing are written, files the snapshot does not have are deleted - a pull request that
 * added a class must not leave it behind - and directories follow. A file whose size and time
 * already match the snapshot is left alone, so restoring the same snapshot twice is fast.
 *
 * Nothing here needs Admidio: the restore page runs it while the installation may be broken.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class SnapshotRestorer
{
    /** @var callable(string,array<int,string|int>):void */
    private $report;

    /**
     * @param callable(string,array<int,string|int>):void|null $report Receives a text key and its
     *        parameters after every step.
     */
    public function __construct(private readonly SnapshotStore $store, ?callable $report = null)
    {
        $this->report = $report ?? static function (): void {
        };
    }

    /**
     * Check that the snapshot is complete before anything is changed.
     * @return array<string,mixed> The manifest.
     */
    public function verify(string $name): array
    {
        $manifest = $this->store->read($name);

        if (($manifest['tablePrefix'] ?? '') !== $this->store->getConfig()->tablePrefix) {
            throw new RuntimeException('The snapshot was taken of the table prefix "' . $manifest['tablePrefix']
                . '", this installation uses "' . $this->store->getConfig()->tablePrefix . '".');
        }
        $postgresSnapshot = ($manifest['dbFormat'] ?? '') === DatabaseDumper::FORMAT_PG_DUMP;
        if ($postgresSnapshot !== $this->store->getConfig()->isPostgres()) {
            throw new RuntimeException('The snapshot was taken of another database system.');
        }
        if (!is_file($this->store->snapshotDirectory($name) . '/' . SnapshotStore::DATABASE_FILE)) {
            throw new RuntimeException('The database dump of the snapshot is missing.');
        }

        $missing = 0;
        foreach ($manifest['files'] as $entry) {
            if ($entry['type'] === 'file' && !is_file($this->store->objectPath($entry['hash']))) {
                ++$missing;
            }
        }
        if ($missing > 0) {
            throw new RuntimeException($missing . ' files of the snapshot are missing in the object store.');
        }

        return $manifest;
    }

    /**
     * Restore the snapshot.
     * @return array{written:int, deleted:int, unchanged:int}
     */
    public function restore(string $name): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->store->lock();
        try {
            $manifest = $this->verify($name);
            ($this->report)('PLG_DEVTOOLS_RESTORE_STEP_VERIFIED', array(count($manifest['files'])));

            (new DatabaseDumper($this->store->getConfig()))->restore(
                $this->store->snapshotDirectory($name) . '/' . SnapshotStore::DATABASE_FILE,
                (string)$manifest['dbFormat']
            );
            ($this->report)('PLG_DEVTOOLS_RESTORE_STEP_DATABASE', array(count($manifest['tables'])));

            $result = $this->restoreFiles($manifest);
            ($this->report)('PLG_DEVTOOLS_RESTORE_STEP_FILES', array($result['written'], $result['deleted'], $result['unchanged']));

            $this->clearCaches();
            ($this->report)('PLG_DEVTOOLS_RESTORE_STEP_CACHES', array());

            return $result;
        } finally {
            $this->store->unlock();
        }
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array{written:int, deleted:int, unchanged:int}
     */
    private function restoreFiles(array $manifest): array
    {
        $config = $this->store->getConfig();
        $root = $config->rootPath;
        $wanted = $manifest['files'];
        $current = (new FileScanner($config, (bool)$manifest['includeData']))->scan();
        $result = array('written' => 0, 'deleted' => 0, 'unchanged' => 0);

        // 1. remove what the snapshot does not have, or has as something else (file <-> directory)
        $paths = array_keys($current);
        rsort($paths, SORT_STRING); // children before their directory
        foreach ($paths as $path) {
            $entry = $current[$path];
            $target = $wanted[$path] ?? null;
            if ($target !== null && $target['type'] === $entry['type']
                && ($entry['type'] !== 'link' || $target['target'] === $entry['target'])) {
                continue;
            }
            if ($entry['type'] === 'dir') {
                SnapshotStore::removeTree($root . '/' . $path);
            } elseif (!@unlink($root . '/' . $path)) {
                throw new RuntimeException('The file ' . $path . ' cannot be deleted.');
            }
            ++$result['deleted'];
            unset($current[$path]);
        }

        // 2. create directories and write files, parents first
        ksort($wanted, SORT_STRING);
        foreach ($wanted as $path => $entry) {
            $absolute = $root . '/' . $path;

            if ($entry['type'] === 'dir') {
                if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
                    throw new RuntimeException('The directory ' . $path . ' cannot be created.');
                }
                @chmod($absolute, (int)$entry['mode']);
                continue;
            }

            if ($entry['type'] === 'link') {
                if (!is_link($absolute)) {
                    $this->ensureParent($absolute);
                    if (!@symlink($entry['target'], $absolute)) {
                        throw new RuntimeException('The symbolic link ' . $path . ' cannot be created.');
                    }
                    ++$result['written'];
                }
                continue;
            }

            $existing = $current[$path] ?? null;
            if ($existing !== null && $existing['size'] === $entry['size']
                && ($existing['mtime'] === $entry['mtime'] || hash_file('sha256', $absolute) === $entry['hash'])) {
                ++$result['unchanged'];
                continue;
            }

            $this->ensureParent($absolute);
            $temporary = $absolute . '.devtools-' . bin2hex(random_bytes(4));
            if (!copy($this->store->objectPath($entry['hash']), $temporary) || !rename($temporary, $absolute)) {
                @unlink($temporary);
                throw new RuntimeException('The file ' . $path . ' cannot be written.');
            }
            @chmod($absolute, (int)$entry['mode']);
            @touch($absolute, (int)$entry['mtime']);
            ++$result['written'];
        }

        return $result;
    }

    private function ensureParent(string $absolute): void
    {
        $directory = dirname($absolute);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The directory ' . $directory . ' cannot be created.');
        }
    }

    /**
     * Compiled templates belong to the code they were compiled from. The restored templates carry
     * the time of the snapshot, which is older than the compiled files, so Smarty would keep using
     * the compiled templates of the pull request. Admidio's own update removes them for the same
     * reason. OPcache of this PHP process is reset as well.
     */
    private function clearCaches(): void
    {
        $templates = $this->store->getConfig()->rootPath . '/' . SnapshotConfig::DATA_FOLDER . '/templates';
        if (is_dir($templates)) {
            foreach (scandir($templates) ?: array() as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    SnapshotStore::removeTree($templates . '/' . $entry);
                }
            }
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
}

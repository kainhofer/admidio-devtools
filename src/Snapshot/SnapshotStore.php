<?php
namespace AdmidioPlugin\DevTools\Snapshot;

use RuntimeException;

/**
 ***********************************************************************************************
 * The snapshots of one installation in the snapshot directory.
 *
 * Layout:
 *     <snapshot directory>/
 *         objects/ab/abcdef…        every file content once, named by its SHA-256
 *         snapshots/<name>/manifest.json
 *         snapshots/<name>/database.gz
 *         restore-key.php           the key of the restore page
 *         .lock                     held while a snapshot is created, deleted or restored
 *
 * A second snapshot of the same installation therefore only costs the files that changed and the
 * dump of the database. An object is removed when no snapshot refers to it any more.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class SnapshotStore
{
    public const MANIFEST_FILE = 'manifest.json';
    public const DATABASE_FILE = 'database.gz';
    public const KEY_FILE = 'restore-key.php';
    public const MANIFEST_VERSION = 1;

    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(private readonly SnapshotConfig $config)
    {
    }

    public function getConfig(): SnapshotConfig
    {
        return $this->config;
    }

    /**
     * Create the snapshot directory. Inside the Admidio directory it is closed to the web server.
     */
    public function ensureDirectory(): void
    {
        $base = $this->config->snapshotPath;
        foreach (array($base, $base . '/objects', $base . '/snapshots') as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new RuntimeException('The snapshot directory ' . $directory . ' cannot be created.');
            }
        }
        if (!is_file($base . '/.htaccess')) {
            file_put_contents($base . '/.htaccess', "Require all denied\n");
        }
        if (!is_file($base . '/index.html')) {
            file_put_contents($base . '/index.html', '');
        }
    }

    /**
     * The key that opens the restore page. It is created with the snapshot directory.
     */
    public function getRestoreKey(bool $create = true): ?string
    {
        $file = $this->config->snapshotPath . '/' . self::KEY_FILE;

        if (is_file($file)) {
            $key = include $file;
            if (is_string($key) && strlen($key) >= 20) {
                return $key;
            }
        }
        if (!$create) {
            return null;
        }

        return $this->renewRestoreKey();
    }

    /**
     * Replace the key of the restore page, e.g. after it was shown to somebody it should not have been.
     */
    public function renewRestoreKey(): string
    {
        $this->ensureDirectory();
        $key = bin2hex(random_bytes(20));
        $file = $this->config->snapshotPath . '/' . self::KEY_FILE;
        file_put_contents($file, "<?php\n// Key of the DevTools restore page. Delete this file to create a new one.\nreturn '" . $key . "';\n", LOCK_EX);
        @chmod($file, 0640);
        return $key;
    }

    public function isValidName(string $name): bool
    {
        return preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}(_[a-z0-9-]{1,60})?$/', $name) === 1;
    }

    /**
     * The snapshots, newest first, without their file lists.
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        $directory = $this->config->snapshotPath . '/snapshots';
        if (!is_dir($directory)) {
            return array();
        }

        $snapshots = array();
        foreach (scandir($directory) ?: array() as $name) {
            if (!$this->isValidName($name) || !is_file($directory . '/' . $name . '/' . self::MANIFEST_FILE)) {
                continue;
            }
            $manifest = $this->read($name);
            unset($manifest['files']);
            $snapshots[] = $manifest;
        }

        usort($snapshots, static fn(array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $snapshots;
    }

    /**
     * The complete manifest of a snapshot.
     * @return array<string,mixed>
     */
    public function read(string $name): array
    {
        if (!$this->isValidName($name)) {
            throw new RuntimeException('Invalid snapshot name.');
        }
        $file = $this->snapshotDirectory($name) . '/' . self::MANIFEST_FILE;
        $manifest = json_decode((string)@file_get_contents($file), true);
        if (!is_array($manifest) || ($manifest['name'] ?? '') !== $name) {
            throw new RuntimeException('The snapshot ' . $name . ' does not exist or is damaged.');
        }
        return $manifest;
    }

    public function snapshotDirectory(string $name): string
    {
        return $this->config->snapshotPath . '/snapshots/' . $name;
    }

    public function objectPath(string $hash): string
    {
        return $this->config->snapshotPath . '/objects/' . substr($hash, 0, 2) . '/' . $hash;
    }

    /**
     * Take a snapshot of the files and the database.
     * @param string $label Short name, becomes part of the snapshot name.
     * @param string $comment Free text.
     * @param bool $includeData Whether adm_my_files is part of the snapshot.
     * @param string $version The Admidio version of the files, if the caller knows it.
     * @return array<string,mixed> The manifest without the file list.
     */
    public function create(string $label, string $comment, bool $includeData, string $version = ''): array
    {
        @set_time_limit(0);
        $this->ensureDirectory();
        $this->lock();
        $directory = '';

        try {
            $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-');
            $name = date('Y-m-d_His') . ($slug !== '' ? '_' . substr($slug, 0, 60) : '');
            $directory = $this->snapshotDirectory($name);
            if (is_dir($directory)) {
                throw new RuntimeException('A snapshot named ' . $name . ' already exists.');
            }
            mkdir($directory, 0770, true);

            $scanner = new FileScanner($this->config, $includeData);
            $files = array();
            $totalSize = 0;
            foreach ($scanner->scan() as $path => $entry) {
                if ($entry['type'] === 'file') {
                    $entry['hash'] = $this->storeObject($this->config->rootPath . '/' . $path);
                    $totalSize += $entry['size'];
                }
                $files[$path] = $entry;
            }

            $dump = (new DatabaseDumper($this->config))->dump($directory . '/' . self::DATABASE_FILE);

            $manifest = array(
                'manifestVersion' => self::MANIFEST_VERSION,
                'name' => $name,
                'label' => $label,
                'comment' => $comment,
                'created' => date('c'),
                'version' => $version,
                'includeData' => $includeData,
                'php' => PHP_VERSION,
                'dbType' => $this->config->dbType,
                'dbVersion' => (string)$this->config->pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'tablePrefix' => $this->config->tablePrefix,
                'dbFormat' => $dump['format'],
                'tables' => $dump['tables'],
                'fileCount' => count(array_filter($files, static fn(array $entry): bool => $entry['type'] === 'file')),
                'fileSize' => $totalSize,
                'databaseSize' => (int)filesize($directory . '/' . self::DATABASE_FILE),
                'files' => $files
            );

            file_put_contents(
                $directory . '/' . self::MANIFEST_FILE,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                LOCK_EX
            );

            // the key of the restore page exists as soon as there is something to restore
            $this->getRestoreKey();

            unset($manifest['files']);
            return $manifest;
        } catch (\Throwable $exception) {
            if ($directory !== '' && is_dir($directory)) {
                self::removeTree($directory);
            }
            throw $exception;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Delete a snapshot and every object no other snapshot needs.
     */
    public function delete(string $name): void
    {
        $this->read($name);
        $this->lock();
        try {
            self::removeTree($this->snapshotDirectory($name));
            $this->collectGarbage();
        } finally {
            $this->unlock();
        }
    }

    /**
     * Remove the objects that no snapshot refers to.
     * @return int The number of removed objects.
     */
    public function collectGarbage(): int
    {
        $used = array();
        foreach ($this->list() as $snapshot) {
            foreach ($this->read($snapshot['name'])['files'] as $entry) {
                if (isset($entry['hash'])) {
                    $used[$entry['hash']] = true;
                }
            }
        }

        $removed = 0;
        $objects = $this->config->snapshotPath . '/objects';
        foreach (scandir($objects) ?: array() as $prefix) {
            if (strlen($prefix) !== 2 || !is_dir($objects . '/' . $prefix)) {
                continue;
            }
            foreach (scandir($objects . '/' . $prefix) ?: array() as $hash) {
                if (strlen($hash) === 64 && !isset($used[$hash])) {
                    unlink($objects . '/' . $prefix . '/' . $hash);
                    ++$removed;
                }
            }
            @rmdir($objects . '/' . $prefix);
        }
        return $removed;
    }

    /**
     * Copy a file into the object store unless its content is already there.
     * @return string The SHA-256 of the content.
     */
    private function storeObject(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new RuntimeException('The file ' . $file . ' cannot be read.');
        }

        $target = $this->objectPath($hash);
        if (!is_file($target)) {
            $directory = dirname($target);
            if (!is_dir($directory)) {
                mkdir($directory, 0770, true);
            }
            $temporary = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (!copy($file, $temporary) || !rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('The file ' . $file . ' cannot be stored in the snapshot.');
            }
        }

        return $hash;
    }

    /**
     * Hold the lock of the snapshot directory. Waits up to 10 seconds for another operation.
     */
    public function lock(): void
    {
        $this->ensureDirectory();
        $handle = fopen($this->config->snapshotPath . '/.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('The snapshot directory cannot be locked.');
        }
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->lockHandle = $handle;
                return;
            }
            usleep(100000);
        }
        fclose($handle);
        throw new RuntimeException('Another snapshot operation is running. Try again when it has finished.');
    }

    public function unlock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    public static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: array() as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeTree($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }
}

<?php
namespace AdmidioPlugin\DevTools\Snapshot;

use FilesystemIterator;

/**
 ***********************************************************************************************
 * Decides which files belong to a snapshot and lists them.
 *
 * The scope is the Admidio directory without
 * - the DevTools plugin itself, whose restore page must never be overwritten by a restore,
 * - .git directories,
 * - adm_my_files, except config.php and cli-config.php. With **includeData** adm_my_files is part of
 *   the snapshot, still without the snapshot directory, adm_my_files/devtools (the data of this
 *   plugin), adm_my_files/temp and the compiled templates in adm_my_files/templates.
 *
 * A restore uses the same scope to decide which existing files it manages, so a file outside the
 * scope is never deleted by a restore.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class FileScanner
{
    /** Files of adm_my_files that every snapshot contains. */
    public const ALWAYS_INCLUDED_DATA_FILES = array('config.php', 'cli-config.php');

    /** Folders of adm_my_files that no snapshot contains. */
    public const EXCLUDED_DATA_FOLDERS = array('devtools', 'temp', 'templates', SnapshotConfig::DEFAULT_SNAPSHOT_FOLDER);

    /** @var array<int,string> Paths relative to the root that are never part of a snapshot. */
    private array $excluded;

    public function __construct(private readonly SnapshotConfig $config, private readonly bool $includeData)
    {
        $excluded = array();

        $pluginPath = $config->relativeToRoot(dirname(__DIR__, 2));
        if ($pluginPath !== null && $pluginPath !== '') {
            $excluded[] = $pluginPath;
        }
        $snapshotPath = $config->relativeToRoot($config->snapshotPath);
        if ($snapshotPath !== null && $snapshotPath !== '') {
            $excluded[] = $snapshotPath;
        }

        $this->excluded = $excluded;
    }

    /**
     * Whether a path relative to the Admidio directory belongs to the scope.
     */
    public function inScope(string $relativePath): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return true;
        }

        foreach ($this->excluded as $excluded) {
            if ($relativePath === $excluded || str_starts_with($relativePath, $excluded . '/')) {
                return false;
            }
        }

        $parts = explode('/', $relativePath);
        if (in_array('.git', $parts, true)) {
            return false;
        }

        if ($parts[0] !== SnapshotConfig::DATA_FOLDER) {
            return true;
        }
        if (count($parts) === 1) {
            return true;
        }
        if (!$this->includeData) {
            return count($parts) === 2 && in_array($parts[1], self::ALWAYS_INCLUDED_DATA_FILES, true);
        }

        return !in_array($parts[1], self::EXCLUDED_DATA_FOLDERS, true);
    }

    /**
     * All entries of the scope, sorted by path.
     *
     * A file is **array('type' => 'file', 'size' => …, 'mtime' => …, 'mode' => …)**, a directory
     * **array('type' => 'dir', 'mode' => …)** and a symbolic link **array('type' => 'link',
     * 'target' => …)**. A symbolic link is stored as a link and never followed.
     * @return array<string,array<string,mixed>>
     */
    public function scan(): array
    {
        $entries = array();
        $this->scanDirectory($this->config->rootPath, '', $entries);
        ksort($entries, SORT_STRING);
        return $entries;
    }

    /**
     * @param array<string,array<string,mixed>> $entries
     */
    private function scanDirectory(string $absolute, string $relative, array &$entries): void
    {
        $iterator = new FilesystemIterator($absolute, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO);

        foreach ($iterator as $info) {
            $name = $info->getFilename();
            $path = $relative === '' ? $name : $relative . '/' . $name;

            if (!$this->inScope($path)) {
                continue;
            }

            if ($info->isLink()) {
                $entries[$path] = array('type' => 'link', 'target' => (string)readlink($info->getPathname()));
            } elseif ($info->isDir()) {
                $entries[$path] = array('type' => 'dir', 'mode' => $info->getPerms() & 0777);
                $this->scanDirectory($info->getPathname(), $path, $entries);
            } elseif ($info->isFile()) {
                $entries[$path] = array(
                    'type' => 'file',
                    'size' => $info->getSize(),
                    'mtime' => $info->getMTime(),
                    'mode' => $info->getPerms() & 0777
                );
            }
        }
    }
}

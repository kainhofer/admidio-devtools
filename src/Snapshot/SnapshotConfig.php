<?php
namespace AdmidioPlugin\DevTools\Snapshot;

use PDO;
use RuntimeException;

/**
 ***********************************************************************************************
 * What a snapshot needs to know about the installation: where it is, where the snapshots are kept
 * and how to reach its database.
 *
 * Everything is read from adm_my_files/config.php and nothing from the database or the preferences,
 * because the restore page has to work when the database or the code of the installation is broken.
 * Like every class below src/Snapshot it uses no Admidio class.
 *
 * The snapshot directory is adm_my_files/snapshots/ unless config.php names another one:
 *     $gDevToolsSnapshotPath = '/var/lib/admidio-snapshots';
 * A relative path is resolved inside adm_my_files.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class SnapshotConfig
{
    public const DATA_FOLDER = 'adm_my_files';
    public const DEFAULT_SNAPSHOT_FOLDER = 'snapshots';

    private ?PDO $pdo = null;

    public function __construct(
        public readonly string $rootPath,
        public readonly string $snapshotPath,
        public readonly string $dbType,
        public readonly string $dbHost,
        public readonly ?int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $tablePrefix,
        public readonly bool $debug
    ) {
    }

    /**
     * Read the configuration of the installation in the given directory.
     * @param string $rootPath The Admidio directory (the one holding adm_my_files).
     * @throws RuntimeException if there is no config.php
     */
    public static function load(string $rootPath): self
    {
        $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/');
        $configFile = $rootPath . '/' . self::DATA_FOLDER . '/config.php';

        if (!is_file($configFile)) {
            throw new RuntimeException('The configuration file ' . $configFile . ' does not exist.');
        }

        $values = self::readConfigFile($configFile);

        $port = $values['g_adm_port'] ?? null;
        $snapshotPath = trim((string)($values['gDevToolsSnapshotPath'] ?? ''));

        return new self(
            $rootPath,
            self::resolveSnapshotPath($rootPath, $snapshotPath),
            (string)($values['gDbType'] ?? 'mysql'),
            (string)($values['g_adm_srv'] ?? 'localhost'),
            ($port === null || (int)$port === 0) ? null : (int)$port,
            (string)($values['g_adm_db'] ?? ''),
            (string)($values['g_adm_usr'] ?? ''),
            (string)($values['g_adm_pw'] ?? ''),
            (string)($values['g_tbl_praefix'] ?? 'adm'),
            !empty($values['gDebug'])
        );
    }

    /**
     * The variables config.php defines, read in a scope of their own so that nothing leaks into the
     * caller. config.php is a plain list of assignments; some installations choose values by host.
     * @return array<string,mixed>
     */
    private static function readConfigFile(string $configFile): array
    {
        return (static function (string $__file): array {
            include $__file;
            unset($__file);
            return get_defined_vars();
        })($configFile);
    }

    /**
     * The directory of the snapshots: the configured one, or adm_my_files/snapshots.
     */
    public static function resolveSnapshotPath(string $rootPath, string $configured): string
    {
        $dataPath = $rootPath . '/' . self::DATA_FOLDER;

        if ($configured === '') {
            return $dataPath . '/' . self::DEFAULT_SNAPSHOT_FOLDER;
        }

        $configured = rtrim(str_replace('\\', '/', $configured), '/');
        $isAbsolute = str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:\//', $configured) === 1;

        return $isAbsolute ? $configured : $dataPath . '/' . $configured;
    }

    public function isPostgres(): bool
    {
        return $this->dbType === 'pgsql' || $this->dbType === 'postgresql';
    }

    /**
     * The path of a file or folder relative to the Admidio directory, or null if it is outside.
     */
    public function relativeToRoot(string $path): ?string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path === $this->rootPath) {
            return '';
        }
        if (str_starts_with($path, $this->rootPath . '/')) {
            return substr($path, strlen($this->rootPath) + 1);
        }
        return null;
    }

    /**
     * A connection of its own to the database of the installation.
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            if ($this->isPostgres()) {
                $dsn = 'pgsql:host=' . $this->dbHost . ';port=' . ($this->dbPort ?? 5432) . ';dbname=' . $this->dbName;
            } else {
                $dsn = 'mysql:host=' . $this->dbHost . ';port=' . ($this->dbPort ?? 3306) . ';dbname=' . $this->dbName . ';charset=utf8mb4';
            }

            $this->pdo = new PDO($dsn, $this->dbUser, $this->dbPassword, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ));
        }

        return $this->pdo;
    }
}

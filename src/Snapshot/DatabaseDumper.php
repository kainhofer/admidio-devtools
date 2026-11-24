<?php
namespace AdmidioPlugin\DevTools\Snapshot;

use PDO;
use RuntimeException;

/**
 ***********************************************************************************************
 * Dumps the tables of the installation into a file and loads them back.
 *
 * Only the tables whose name starts with the table prefix of the installation and an underscore
 * are dumped, dropped and restored; other tables in the same database are never touched.
 *
 * MySQL and MariaDB are dumped with PDO, so no mysqldump binary is needed. The dump is a gzip file
 * with one JSON-encoded SQL statement per line: that is safe to split whatever the data contains,
 * and it can be streamed in both directions. Values that are not valid UTF-8 (photos and other
 * binary columns) are written as hex literals.
 *
 * PostgreSQL is dumped with pg_dump and restored with psql, if PHP may run them.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class DatabaseDumper
{
    public const FORMAT_JSON_LINES = 'jsonl';
    public const FORMAT_PG_DUMP = 'pg_dump';

    private const ROWS_PER_INSERT = 200;

    public function __construct(private readonly SnapshotConfig $config)
    {
    }

    /**
     * The tables of the installation.
     * @return array<int,string>
     */
    public function getTables(): array
    {
        $pdo = $this->config->pdo();
        $prefix = $this->config->tablePrefix . '_';

        if ($this->config->isPostgres()) {
            $statement = $pdo->prepare('SELECT table_name FROM information_schema.tables
                WHERE table_schema = current_schema() AND table_type = \'BASE TABLE\'');
            $statement->execute();
        } else {
            $statement = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
        }

        $tables = array();
        foreach ($statement->fetchAll(PDO::FETCH_NUM) as $row) {
            if (str_starts_with((string)$row[0], $prefix)) {
                $tables[] = (string)$row[0];
            }
        }
        sort($tables, SORT_STRING);
        return $tables;
    }

    /**
     * Write the dump.
     * @param string $file The gzip file to create.
     * @return array{format:string, tables:array<string,int>} The format and the number of rows per table.
     */
    public function dump(string $file): array
    {
        if ($this->config->isPostgres()) {
            return array('format' => self::FORMAT_PG_DUMP, 'tables' => $this->dumpPostgres($file));
        }

        return array('format' => self::FORMAT_JSON_LINES, 'tables' => $this->dumpMysql($file));
    }

    /**
     * Replace the tables of the installation with those of the dump: every table with the prefix is
     * dropped first, also those the dump does not contain (tables a pull request added).
     */
    public function restore(string $file, string $format): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('The database dump ' . $file . ' does not exist.');
        }

        if ($format === self::FORMAT_PG_DUMP) {
            $this->restorePostgres($file);
            return;
        }

        $pdo = $this->config->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($this->getTables() as $table) {
                $pdo->exec('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
            }

            $handle = gzopen($file, 'rb');
            if ($handle === false) {
                throw new RuntimeException('The database dump ' . $file . ' cannot be read.');
            }
            try {
                $lineNumber = 0;
                while (($line = gzgets($handle)) !== false) {
                    ++$lineNumber;
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $statement = json_decode($line, false, 2, JSON_THROW_ON_ERROR);
                    if (!is_string($statement)) {
                        throw new RuntimeException('Line ' . $lineNumber . ' of the database dump is not a statement.');
                    }
                    $pdo->exec($statement);
                }
            } finally {
                gzclose($handle);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * @return array<string,int>
     */
    private function dumpMysql(string $file): array
    {
        $pdo = $this->config->pdo();
        $handle = gzopen($file, 'wb6');
        if ($handle === false) {
            throw new RuntimeException('The database dump ' . $file . ' cannot be written.');
        }

        $counts = array();
        try {
            $this->writeStatement($handle, 'SET FOREIGN_KEY_CHECKS = 0');
            $this->writeStatement($handle, 'SET NAMES utf8mb4');

            foreach ($this->getTables() as $table) {
                $quoted = $this->quoteIdentifier($table);
                $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM);

                $this->writeStatement($handle, 'DROP TABLE IF EXISTS ' . $quoted);
                $this->writeStatement($handle, (string)$create[1]);

                // Large tables are read unbuffered, so that the whole table never sits in memory.
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                try {
                    $rows = $pdo->query('SELECT * FROM ' . $quoted, PDO::FETCH_ASSOC);
                    $values = array();
                    $columns = null;
                    $count = 0;
                    foreach ($rows as $row) {
                        if ($columns === null) {
                            $columns = implode(', ', array_map(array($this, 'quoteIdentifier'), array_keys($row)));
                        }
                        $values[] = '(' . implode(', ', array_map(array($this, 'quoteValue'), array_values($row))) . ')';
                        ++$count;
                        if (count($values) >= self::ROWS_PER_INSERT) {
                            $this->writeStatement($handle, 'INSERT INTO ' . $quoted . ' (' . $columns . ') VALUES ' . implode(', ', $values));
                            $values = array();
                        }
                    }
                    $rows->closeCursor();
                    if ($values !== array()) {
                        $this->writeStatement($handle, 'INSERT INTO ' . $quoted . ' (' . $columns . ') VALUES ' . implode(', ', $values));
                    }
                } finally {
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                }
                $counts[$table] = $count;
            }

            $this->writeStatement($handle, 'SET FOREIGN_KEY_CHECKS = 1');
        } finally {
            gzclose($handle);
        }

        return $counts;
    }

    /**
     * @param resource $handle
     */
    private function writeStatement($handle, string $statement): void
    {
        gzwrite($handle, json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = (string)$value;
        if (!mb_check_encoding($value, 'UTF-8')) {
            return $value === '' ? "''" : 'X\'' . bin2hex($value) . '\'';
        }

        return $this->config->pdo()->quote($value);
    }

    /**
     * @return array<string,int>
     */
    private function dumpPostgres(string $file): array
    {
        $tables = $this->getTables();
        $counts = array();
        $pdo = $this->config->pdo();
        foreach ($tables as $table) {
            $counts[$table] = (int)$pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $table) . '"')->fetchColumn();
        }

        $command = array(
            self::findBinary('pg_dump'), '--no-owner', '--no-privileges', '--clean', '--if-exists',
            '--host=' . $this->config->dbHost, '--port=' . ($this->config->dbPort ?? 5432),
            '--username=' . $this->config->dbUser, '--table=' . $this->config->tablePrefix . '\_*', $this->config->dbName
        );

        $handle = gzopen($file, 'wb6');
        if ($handle === false) {
            throw new RuntimeException('The database dump ' . $file . ' cannot be written.');
        }
        try {
            $this->runProcess($command, null, static function (string $chunk) use ($handle): void {
                gzwrite($handle, $chunk);
            });
        } finally {
            gzclose($handle);
        }

        return $counts;
    }

    private function restorePostgres(string $file): void
    {
        $pdo = $this->config->pdo();
        foreach ($this->getTables() as $table) {
            $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '" CASCADE');
        }

        $command = array(
            self::findBinary('psql'), '--quiet', '--set=ON_ERROR_STOP=1',
            '--host=' . $this->config->dbHost, '--port=' . ($this->config->dbPort ?? 5432),
            '--username=' . $this->config->dbUser, '--dbname=' . $this->config->dbName
        );

        $this->runProcess($command, $file, null);
    }

    /**
     * Run pg_dump or psql. The password is handed over in the environment, never on the command line.
     * @param array<int,string> $command
     * @param string|null $gzipInput A gzip file whose content is the standard input.
     * @param callable|null $onOutput Receives the standard output in chunks.
     */
    private function runProcess(array $command, ?string $gzipInput, ?callable $onOutput): void
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('PHP may not start other programs (proc_open), so PostgreSQL cannot be dumped or restored here.');
        }

        $environment = getenv();
        $environment['PGPASSWORD'] = $this->config->dbPassword;
        $errorFile = tempnam(sys_get_temp_dir(), 'devtools');

        $process = proc_open($command, array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('file', $errorFile, 'w')
        ), $pipes, null, $environment);

        if (!is_resource($process)) {
            throw new RuntimeException('The program ' . $command[0] . ' cannot be started.');
        }

        if ($gzipInput !== null) {
            $input = gzopen($gzipInput, 'rb');
            while (!gzeof($input)) {
                fwrite($pipes[0], (string)gzread($input, 65536));
            }
            gzclose($input);
        }
        fclose($pipes[0]);

        while (!feof($pipes[1])) {
            $chunk = (string)fread($pipes[1], 65536);
            if ($chunk !== '' && $onOutput !== null) {
                $onOutput($chunk);
            }
        }
        fclose($pipes[1]);

        $exitCode = proc_close($process);
        $errors = trim((string)file_get_contents($errorFile));
        unlink($errorFile);

        if ($exitCode !== 0) {
            throw new RuntimeException(basename($command[0]) . ' failed (' . $exitCode . '): ' . $errors);
        }
    }

    private static function findBinary(string $name): string
    {
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $directory) {
            if ($directory !== '' && is_executable($directory . '/' . $name)) {
                return $directory . '/' . $name;
            }
        }
        foreach (array('/usr/bin/', '/usr/local/bin/', '/usr/lib/postgresql/bin/') as $directory) {
            if (is_executable($directory . $name)) {
                return $directory . $name;
            }
        }
        throw new RuntimeException('The program ' . $name . ' was not found, so PostgreSQL cannot be dumped or restored here.');
    }
}

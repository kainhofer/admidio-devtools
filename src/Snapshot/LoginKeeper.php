<?php
namespace AdmidioPlugin\DevTools\Snapshot;

use PDO;

/**
 ***********************************************************************************************
 * Keeps the logins across a restore.
 *
 * The sessions and the "remember me" records are tables like any other, so a restore would replace
 * them with those of the time of the snapshot and log everybody out. A tester who restores between
 * two pull requests wants to stay logged in, so the current rows are read before the restore and
 * written back afterwards:
 *
 * - only the columns the restored tables still have are written (a pull request may have added one),
 * - only rows whose user and organization exist in the restored database are kept,
 * - a row of the snapshot with the same ID gives way,
 * - every kept session is marked for reload (ses_reload), so the next request builds user,
 *   organization, preferences and menu anew from the restored database instead of using what the
 *   PHP session still holds.
 *
 * Uses no Admidio class: it runs on the restore page.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class LoginKeeper
{
    /** table suffix => array(primary key, user column, organization column) */
    private const TABLES = array(
        'sessions' => array('ses_id', 'ses_usr_id', 'ses_org_id'),
        'auto_login' => array('atl_id', 'atl_usr_id', 'atl_org_id')
    );

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $rows = array();

    public function __construct(private readonly SnapshotConfig $config)
    {
    }

    /**
     * Read the current logins. Call before the database is restored.
     */
    public function capture(): void
    {
        $pdo = $this->config->pdo();
        $this->rows = array();

        foreach (array_keys(self::TABLES) as $suffix) {
            $table = $this->table($suffix);
            if ($this->getColumns($table) === array()) {
                continue;
            }
            $rows = array();
            foreach ($pdo->query('SELECT * FROM ' . $this->quote($table))->fetchAll(PDO::FETCH_ASSOC) as $row) {
                foreach ($row as $column => $value) {
                    if (is_resource($value)) {
                        // PostgreSQL returns bytea as a stream
                        $row[$column] = stream_get_contents($value);
                    }
                }
                $rows[] = $row;
            }
            $this->rows[$suffix] = $rows;
        }
    }

    /**
     * Write the captured logins into the restored database.
     * @return int The number of kept sessions.
     */
    public function restore(): int
    {
        $pdo = $this->config->pdo();
        $users = $this->getIds($this->table('users'), 'usr_id');
        $organizations = $this->getIds($this->table('organizations'), 'org_id');
        $keptSessions = 0;

        foreach (self::TABLES as $suffix => list($primaryKey, $userColumn, $organizationColumn)) {
            $table = $this->table($suffix);
            $columns = $this->getColumns($table);
            if ($columns === array() || empty($this->rows[$suffix])) {
                continue;
            }

            foreach ($this->rows[$suffix] as $row) {
                if (($row[$userColumn] !== null && !isset($users[(int)$row[$userColumn]]))
                    || !isset($organizations[(int)$row[$organizationColumn]])) {
                    continue;
                }
                if ($suffix === 'sessions' && in_array('ses_reload', $columns, true)) {
                    $row['ses_reload'] = 1;
                }

                $values = array_intersect_key($row, array_flip($columns));
                $pdo->prepare('DELETE FROM ' . $this->quote($table) . ' WHERE ' . $this->quote($primaryKey) . ' = ?')
                    ->execute(array($row[$primaryKey]));

                $statement = $pdo->prepare('INSERT INTO ' . $this->quote($table)
                    . ' (' . implode(', ', array_map(array($this, 'quote'), array_keys($values))) . ')'
                    . ' VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')');
                $position = 0;
                foreach ($values as $value) {
                    ++$position;
                    if ($value === null) {
                        $statement->bindValue($position, null, PDO::PARAM_NULL);
                    } elseif (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        $statement->bindValue($position, $value, PDO::PARAM_LOB);
                    } elseif (is_bool($value)) {
                        $statement->bindValue($position, $value, PDO::PARAM_BOOL);
                    } else {
                        $statement->bindValue($position, $value);
                    }
                }
                $statement->execute();

                if ($suffix === 'sessions') {
                    ++$keptSessions;
                }
            }

            if ($this->config->isPostgres()) {
                // explicit IDs do not advance the sequence
                $pdo->query('SELECT setval(pg_get_serial_sequence(' . $pdo->quote($table) . ', ' . $pdo->quote($primaryKey) . '), '
                    . 'COALESCE((SELECT MAX(' . $this->quote($primaryKey) . ') FROM ' . $this->quote($table) . '), 1))');
            }
        }

        return $keptSessions;
    }

    private function table(string $suffix): string
    {
        return $this->config->tablePrefix . '_' . $suffix;
    }

    private function quote(string $identifier): string
    {
        return $this->config->isPostgres()
            ? '"' . str_replace('"', '""', $identifier) . '"'
            : '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * The columns of a table, empty if it does not exist.
     * @return array<int,string>
     */
    private function getColumns(string $table): array
    {
        $schema = $this->config->isPostgres() ? 'current_schema()' : 'DATABASE()';
        $statement = $this->config->pdo()->prepare('SELECT column_name FROM information_schema.columns
            WHERE table_schema = ' . $schema . ' AND table_name = ? ORDER BY ordinal_position');
        $statement->execute(array($table));
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array<int,true>
     */
    private function getIds(string $table, string $column): array
    {
        if ($this->getColumns($table) === array()) {
            return array();
        }
        $ids = array();
        foreach ($this->config->pdo()->query('SELECT ' . $this->quote($column) . ' FROM ' . $this->quote($table))->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[(int)$id] = true;
        }
        return $ids;
    }
}

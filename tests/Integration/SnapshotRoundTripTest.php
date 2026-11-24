<?php
namespace AdmidioPlugin\DevTools\Tests\Integration;

use AdmidioPlugin\DevTools\Snapshot\SnapshotConfig;
use AdmidioPlugin\DevTools\Snapshot\SnapshotRestorer;
use AdmidioPlugin\DevTools\Snapshot\SnapshotStore;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

require_once dirname(__DIR__) . '/Support/autoload.php';
require_once dirname(__DIR__, 4) . '/tests/env.php';

/**
 * A snapshot of a small installation in a temporary directory, with tables of their own prefix in
 * the MySQL/MariaDB test database: take it, change files and tables, restore it, compare.
 */
final class SnapshotRoundTripTest extends TestCase
{
    private const PREFIX = 'dvtrt';

    private string $root = '';
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        try {
            admidioTestLoadEnvironment();
        } catch (Throwable $exception) {
            $this->markTestSkipped('The test environment is not configured: ' . $exception->getMessage());
        }
        $db = admidioTestDatabaseConfig('mariadb');
        try {
            $this->pdo = new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['database'] . ';charset=utf8mb4',
                $db['user'], (string)$db['password'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch (Throwable $exception) {
            $this->markTestSkipped('The MariaDB test database is not reachable: ' . $exception->getMessage());
        }

        $this->root = sys_get_temp_dir() . '/devtools-roundtrip-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/adm_my_files/documents', 0777, true);
        mkdir($this->root . '/src', 0777, true);
        file_put_contents($this->root . '/adm_my_files/config.php', '<?php' . "\n"
            . '$gDbType = \'mysql\';' . "\n"
            . '$g_adm_srv = ' . var_export($db['host'], true) . ";\n"
            . '$g_adm_port = ' . (int)$db['port'] . ";\n"
            . '$g_adm_db = ' . var_export($db['database'], true) . ";\n"
            . '$g_adm_usr = ' . var_export($db['user'], true) . ";\n"
            . '$g_adm_pw = ' . var_export((string)$db['password'], true) . ";\n"
            . '$g_tbl_praefix = \'' . self::PREFIX . "';\n"
            . '$gDebug = true;' . "\n");
        file_put_contents($this->root . '/src/a.php', 'original a');
        file_put_contents($this->root . '/src/b.php', 'original b');
        file_put_contents($this->root . '/adm_my_files/documents/doc.txt', 'document');

        $this->dropTables();
        $this->pdo->exec('CREATE TABLE ' . self::PREFIX . '_items (id INT PRIMARY KEY, name VARCHAR(50), data BLOB NULL)');
        $this->pdo->exec('CREATE TABLE ' . self::PREFIX . '_links (id INT PRIMARY KEY, item_id INT, FOREIGN KEY (item_id) REFERENCES ' . self::PREFIX . '_items (id))');
        $insert = $this->pdo->prepare('INSERT INTO ' . self::PREFIX . '_items VALUES (?, ?, ?)');
        $insert->execute(array(1, "O'Brien; \n DROP", "\x00\xff\x10binary"));
        $insert->execute(array(2, 'Ümläut ✓', null));
        $this->pdo->exec('INSERT INTO ' . self::PREFIX . '_links VALUES (1, 2)');
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->dropTables();
        }
        if ($this->root !== '') {
            SnapshotStore::removeTree($this->root);
        }
    }

    private function dropTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array('items', 'links', 'added') as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS ' . self::PREFIX . '_' . $table);
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testRestoreBringsBackFilesAndDatabase(): void
    {
        $store = new SnapshotStore(SnapshotConfig::load($this->root));
        $manifest = $store->create('Base line', 'before the PR', false, '6.0.0');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}_\d{6}_base-line$/', $manifest['name']);
        $this->assertSame(array(self::PREFIX . '_items' => 2, self::PREFIX . '_links' => 1), $manifest['tables']);
        $this->assertNotNull($store->getRestoreKey(false));
        $full = $store->read($manifest['name']);
        $this->assertArrayHasKey('adm_my_files/config.php', $full['files']);
        $this->assertArrayNotHasKey('adm_my_files/documents/doc.txt', $full['files']);

        // what a pull request does
        file_put_contents($this->root . '/src/a.php', 'changed a');
        unlink($this->root . '/src/b.php');
        mkdir($this->root . '/src/New');
        file_put_contents($this->root . '/src/New/c.php', 'added c');
        file_put_contents($this->root . '/adm_my_files/documents/new.txt', 'uploaded meanwhile');
        $this->pdo->exec('UPDATE ' . self::PREFIX . '_items SET name = \'changed\' WHERE id = 2');
        $this->pdo->exec('INSERT INTO ' . self::PREFIX . '_items VALUES (3, \'new\', NULL)');
        $this->pdo->exec('CREATE TABLE ' . self::PREFIX . '_added (id INT)');

        $steps = array();
        $result = (new SnapshotRestorer($store, static function (string $key) use (&$steps): void {
            $steps[] = $key;
        }))->restore($manifest['name']);

        $this->assertSame(array('PLG_DEVTOOLS_RESTORE_STEP_VERIFIED', 'PLG_DEVTOOLS_RESTORE_STEP_DATABASE',
            'PLG_DEVTOOLS_RESTORE_STEP_FILES', 'PLG_DEVTOOLS_RESTORE_STEP_CACHES'), $steps);
        $this->assertSame('original a', file_get_contents($this->root . '/src/a.php'));
        $this->assertSame('original b', file_get_contents($this->root . '/src/b.php'));
        $this->assertDirectoryDoesNotExist($this->root . '/src/New');
        $this->assertFileExists($this->root . '/adm_my_files/documents/new.txt', 'adm_my_files is outside the scope');
        $this->assertGreaterThan(0, $result['deleted']);

        $rows = $this->pdo->query('SELECT id, name, data FROM ' . self::PREFIX . '_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
        $this->assertSame("O'Brien; \n DROP", $rows[0]['name']);
        $this->assertSame("\x00\xff\x10binary", $rows[0]['data']);
        $this->assertSame('Ümläut ✓', $rows[1]['name']);
        $this->assertNull($rows[1]['data']);
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = \'' . self::PREFIX . '_added\'')->fetchColumn());
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM ' . self::PREFIX . '_links')->fetchColumn());

        // restoring again changes nothing, and deleting removes the objects
        $again = (new SnapshotRestorer($store))->restore($manifest['name']);
        $this->assertSame(0, $again['written']);
        $store->delete($manifest['name']);
        $this->assertSame(array(), $store->list());
        $this->assertSame(array(), glob($this->root . '/adm_my_files/snapshots/objects/*/*') ?: array());
    }
}

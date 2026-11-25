<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use AdmidioPlugin\DevTools\Snapshot\FileScanner;
use AdmidioPlugin\DevTools\Snapshot\SnapshotConfig;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/autoload.php';

final class FileScannerTest extends TestCase
{
    private function config(string $root, string $snapshotPath = ''): SnapshotConfig
    {
        return new SnapshotConfig($root, $snapshotPath !== '' ? $snapshotPath : $root . '/adm_my_files/snapshots',
            'mysql', 'localhost', null, 'db', 'user', '', 'adm', true);
    }

    public function testScopeWithoutData(): void
    {
        $scanner = new FileScanner($this->config('/srv/admidio'), false);

        $this->assertTrue($scanner->inScope('src/Hooks/Hooks.php'));
        $this->assertTrue($scanner->inScope('adm_my_files'));
        $this->assertTrue($scanner->inScope('adm_my_files/config.php'));
        $this->assertTrue($scanner->inScope('adm_my_files/cli-config.php'));
        $this->assertFalse($scanner->inScope('adm_my_files/documents_demo/file.pdf'));
        $this->assertFalse($scanner->inScope('adm_my_files/logs'));
        $this->assertFalse($scanner->inScope('plugins/impersonate/.git/HEAD'));
        $this->assertFalse($scanner->inScope('.git'));
        $this->assertTrue($scanner->inScope('plugins/impersonate/plugin.php'));
    }

    public function testScopeWithData(): void
    {
        $scanner = new FileScanner($this->config('/srv/admidio'), true);

        $this->assertTrue($scanner->inScope('adm_my_files/documents_demo/file.pdf'));
        $this->assertFalse($scanner->inScope('adm_my_files/snapshots/objects'));
        $this->assertFalse($scanner->inScope('adm_my_files/devtools/mails/x.json'));
        $this->assertFalse($scanner->inScope('adm_my_files/temp/upload'));
        $this->assertFalse($scanner->inScope('adm_my_files/templates/compile/x.php'));
    }

    public function testConfiguredSnapshotDirectoryInsideTheTreeIsExcluded(): void
    {
        $scanner = new FileScanner($this->config('/srv/admidio', '/srv/admidio/backup/snaps'), false);

        $this->assertFalse($scanner->inScope('backup/snaps/objects/ab/abc'));
        $this->assertTrue($scanner->inScope('backup/other'));
    }

    public function testSnapshotPathResolution(): void
    {
        $this->assertSame('/srv/a/adm_my_files/snapshots', SnapshotConfig::resolveSnapshotPath('/srv/a', ''));
        $this->assertSame('/var/snaps', SnapshotConfig::resolveSnapshotPath('/srv/a', '/var/snaps/'));
        $this->assertSame('/srv/a/adm_my_files/snaps', SnapshotConfig::resolveSnapshotPath('/srv/a', 'snaps'));
    }

    public function testSnapshotDirectoryChosenOnThePage(): void
    {
        $root = sys_get_temp_dir() . '/devtools-pointer-' . bin2hex(random_bytes(4));
        mkdir($root . '/adm_my_files', 0777, true);
        file_put_contents($root . '/adm_my_files/config.php', "<?php\n\$g_tbl_praefix = 'adm';\n");

        try {
            $this->assertSame(SnapshotConfig::SOURCE_DEFAULT, SnapshotConfig::load($root)->snapshotPathSource);

            SnapshotConfig::writePointer($root, '/var/snaps');
            $config = SnapshotConfig::load($root);
            $this->assertSame('/var/snaps', $config->snapshotPath);
            $this->assertSame(SnapshotConfig::SOURCE_PAGE, $config->snapshotPathSource);
            $this->assertFalse((new FileScanner($config, true))->inScope('adm_my_files/devtools/snapshot-path.php'), 'no restore may change the choice');

            file_put_contents($root . '/adm_my_files/config.php', "<?php\n\$g_tbl_praefix = 'adm';\n\$gDevToolsSnapshotPath = '/srv/other';\n");
            $config = SnapshotConfig::load($root);
            $this->assertSame('/srv/other', $config->snapshotPath, 'config.php takes precedence');
            $this->assertSame(SnapshotConfig::SOURCE_CONFIG, $config->snapshotPathSource);

            SnapshotConfig::writePointer($root, '');
            $this->assertSame('', SnapshotConfig::readPointer($root));
        } finally {
            \AdmidioPlugin\DevTools\Snapshot\SnapshotStore::removeTree($root);
        }
    }
}

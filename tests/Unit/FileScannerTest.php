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
}

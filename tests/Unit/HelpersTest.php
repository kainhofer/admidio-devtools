<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use AdmidioPlugin\DevTools\Hooks\DispatchSiteScanner;
use AdmidioPlugin\DevTools\Hooks\HookTracer;
use AdmidioPlugin\DevTools\Logs\LogReader;
use AdmidioPlugin\DevTools\Tasks\CliRunner;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/autoload.php';

final class HelpersTest extends TestCase
{
    public function testSplitArguments(): void
    {
        $this->assertSame(
            array('user:list', '--role=Members of board', '--format=json', 'a b'),
            CliRunner::splitArguments('user:list "--role=Members of board" --format=json \'a b\'')
        );
        $this->assertSame(array(), CliRunner::splitArguments('   '));
    }

    public function testEntityHooksAreRecognised(): void
    {
        $this->assertTrue(DispatchSiteScanner::isEntityHook('user_created'));
        $this->assertTrue(DispatchSiteScanner::isEntityHook('entity_delete_failed'));
        $this->assertFalse(DispatchSiteScanner::isEntityHook('page_headline'));
    }

    public function testSummarize(): void
    {
        $this->assertSame('null', HookTracer::summarize(null));
        $this->assertSame('"abc"', HookTracer::summarize('abc'));
        $this->assertSame('array(2) [a, b]', HookTracer::summarize(array('a' => 1, 'b' => 2)));
        $this->assertSame('stdClass', HookTracer::summarize(new \stdClass()));
    }

    public function testLogReaderParsesMonologAndPhpLines(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($file, implode("\n", array(
            '[2026-11-24 10:00:00.000000] Admidio.INFO: first',
            '[2026-11-24 10:00:01.000000] Admidio.ERROR: second',
            '#0 stack line',
            '[24-Nov-2026 10:00:02 UTC] PHP Warning:  third',
            ''
        )));

        $reader = new LogReader(sys_get_temp_dir());
        $entries = $reader->read($file);
        unlink($file);

        $this->assertCount(3, $entries);
        $this->assertSame('WARNING', $entries[0]['level']);
        $this->assertSame("second\n#0 stack line", $entries[1]['text']);
        $this->assertSame('INFO', $entries[2]['level']);
    }

    public function testLogReaderFiltersByLevelAndText(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($file, "[2026-11-24 10:00:00.0] Admidio.INFO: a\n[2026-11-24 10:00:01.0] Admidio.ERROR: b\n[2026-11-24 10:00:01.0] Admidio.WARNING: c\n");
        $reader = new LogReader(sys_get_temp_dir());

        $this->assertCount(2, $reader->read($file, 10, 'WARNING'));
        $this->assertCount(1, $reader->read($file, 10, '', 'B'));
        unlink($file);
    }
}

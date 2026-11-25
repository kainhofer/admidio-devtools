<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use AdmidioPlugin\DevTools\Snapshot\SnapshotStore;
use AdmidioPlugin\DevTools\Tasks\MaintenanceLog;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/autoload.php';

final class MaintenanceLogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/devtools-cli-log-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        $lines = array();
        $base = strtotime('-1 day 10:15:05');
        for ($hour = 0; $hour < 5; ++$hour) {
            $time = date('Y-m-d H:i:s', $base + $hour * 3600);
            $lines[] = '[' . $time . '] account=www-data(1000) exit=' . ($hour === 4 ? 6 : 0) . ' actor=admin command=maintenance:run --organization=DEMO --yes --no-interaction --quiet';
        }
        $lines[] = '[' . date('Y-m-d H:i:s', $base + 5 * 3600) . '] account=www-data(1000) exit=0 actor=admin command=maintenance:run --job=sessions --job=categories --organization=TEST --yes';
        $lines[] = '[' . date('Y-m-d H:i:s', $base) . '] account=www-data(1000) exit=0 actor=admin command=user:list';
        file_put_contents($this->directory . '/admidio-cli-' . date('Y-m-d', $base) . '.log', implode("\n", $lines) . "\n");
    }

    protected function tearDown(): void
    {
        SnapshotStore::removeTree($this->directory);
    }

    public function testRunsOrganizationsAndJobs(): void
    {
        $log = new MaintenanceLog($this->directory);

        $runs = $log->getRuns();
        $this->assertCount(6, $runs);
        $this->assertSame(array('sessions', 'categories'), $runs[5]['jobs']);
        $this->assertSame('TEST', $runs[5]['organization']);

        $organizations = $log->getOrganizations($runs[4]['timestamp'] + 600);
        $this->assertSame(array('DEMO'), array_keys($organizations), 'a run of single jobs is no run of all jobs');
        $demo = $organizations['DEMO'];
        $this->assertSame(5, $demo['runs']);
        $this->assertSame(1, $demo['failed']);
        $this->assertSame(6, $demo['last']['exitCode']);
        $this->assertSame($runs[3]['timestamp'], $demo['lastSuccess']['timestamp']);
        $this->assertSame(3600, $demo['interval']);
        $this->assertFalse($demo['overdue']);
        $this->assertTrue($log->getOrganizations($runs[4]['timestamp'] + 3 * 3600)['DEMO']['overdue']);

        $last = $log->getLastRunPerJob(array('sessions', 'birthdays'));
        $this->assertSame('TEST', $last['sessions']['organization']);
        $this->assertSame('DEMO', $last['birthdays']['organization']);
    }
}

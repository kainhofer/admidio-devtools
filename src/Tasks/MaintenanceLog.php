<?php
namespace AdmidioPlugin\DevTools\Tasks;

/**
 ***********************************************************************************************
 * What the log of the command line tells about the runs of the maintenance jobs.
 *
 * The core keeps no history of maintenance:run, but the CLI log ($gCliLogFile, by default
 * adm_my_files/logs/admidio-cli-<date>.log) has one line per invocation with time, exit code and
 * command line:
 *     [2026-11-25 16:20:08] account=www-data(1000) exit=0 actor=admin command=maintenance:run --organization=TEST --yes
 * From these lines come the runs per organization, the last run of each job, and whether the runs
 * that cron starts have stopped. The log does not say which job of a run failed - exit code 6 only
 * says that at least one did.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class MaintenanceLog
{
    public const DAYS = 14;
    public const ALL_JOBS = '*';

    private const LINE = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] account=(\S+) exit=(-?\d+) actor=(\S*) command=maintenance:run(.*)$/';

    /** @var array<int,array{time:string, timestamp:int, exitCode:int, account:string, actor:string, organization:string, jobs:array<int,string>}>|null */
    private ?array $runs = null;

    public function __construct(private readonly string $logDirectory)
    {
    }

    /**
     * The runs of maintenance:run of the last DAYS days, oldest first. A run without --job ran every
     * job (jobs = array('*')); one without --organization ran in the default organization ('').
     * @return array<int,array{time:string, timestamp:int, exitCode:int, account:string, actor:string, organization:string, jobs:array<int,string>}>
     */
    public function getRuns(): array
    {
        if ($this->runs !== null) {
            return $this->runs;
        }

        $files = glob($this->logDirectory . '/admidio-cli*.log') ?: array();
        $oldest = time() - self::DAYS * 86400;
        $this->runs = array();
        foreach ($files as $file) {
            if (filemtime($file) < $oldest) {
                continue;
            }
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                if (!str_contains($line, 'command=maintenance:run') || !preg_match(self::LINE, rtrim($line), $match)) {
                    continue;
                }
                $timestamp = (int)strtotime($match[1]);
                if ($timestamp < $oldest) {
                    continue;
                }
                $this->runs[] = self::parseRun($match, $timestamp);
            }
            fclose($handle);
        }

        usort($this->runs, static fn(array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);
        return $this->runs;
    }

    /**
     * @param array<int,string> $match
     * @return array{time:string, timestamp:int, exitCode:int, account:string, actor:string, organization:string, jobs:array<int,string>}
     */
    private static function parseRun(array $match, int $timestamp): array
    {
        $arguments = $match[5];
        preg_match('/--organization[= ]([^\s]+)/', $arguments, $organization);
        preg_match_all('/--job[= ]([^\s]+)/', $arguments, $jobs);

        return array(
            'time' => $match[1],
            'timestamp' => $timestamp,
            'exitCode' => (int)$match[3],
            'account' => $match[2],
            'actor' => $match[4],
            'organization' => $organization[1] ?? '',
            'jobs' => $jobs[1] !== array() ? $jobs[1] : array(self::ALL_JOBS)
        );
    }

    /**
     * Per organization: the last run of all jobs, the last successful one, the usual interval and
     * whether the next run is overdue.
     * @return array<string,array{organization:string, runs:int, failed:int, last:?array, lastSuccess:?array, interval:?int, overdue:bool}>
     */
    public function getOrganizations(int $now): array
    {
        $byOrganization = array();
        foreach ($this->getRuns() as $run) {
            if ($run['jobs'] !== array(self::ALL_JOBS)) {
                continue;
            }
            $byOrganization[$run['organization']][] = $run;
        }

        $result = array();
        foreach ($byOrganization as $organization => $runs) {
            $last = end($runs);
            $successful = array_values(array_filter($runs, static fn(array $run): bool => $run['exitCode'] === 0));
            $interval = self::typicalInterval(array_column($runs, 'timestamp'));
            $result[$organization] = array(
                'organization' => (string)$organization,
                'runs' => count($runs),
                'failed' => count($runs) - count($successful),
                'last' => $last,
                'lastSuccess' => $successful !== array() ? end($successful) : null,
                'interval' => $interval,
                // a run that cron starts regularly and that has not come for twice its interval
                'overdue' => $interval !== null && $now - $last['timestamp'] > max(2 * $interval, 3600)
            );
        }
        ksort($result, SORT_STRING);
        return $result;
    }

    /**
     * The last run of every job: the latest run that included it, explicitly or as part of all jobs.
     * @param array<int,string> $jobNames
     * @return array<string,array<string,mixed>|null>
     */
    public function getLastRunPerJob(array $jobNames): array
    {
        $last = array_fill_keys($jobNames, null);
        foreach ($this->getRuns() as $run) {
            foreach ($jobNames as $job) {
                if ($run['jobs'] === array(self::ALL_JOBS) || in_array($job, $run['jobs'], true)) {
                    $last[$job] = $run;
                }
            }
        }
        return $last;
    }

    /**
     * The median distance between runs, in seconds; null with fewer than three runs.
     * @param array<int,int> $timestamps In ascending order.
     */
    public static function typicalInterval(array $timestamps): ?int
    {
        if (count($timestamps) < 3) {
            return null;
        }
        $gaps = array();
        for ($index = 1, $count = count($timestamps); $index < $count; ++$index) {
            $gaps[] = $timestamps[$index] - $timestamps[$index - 1];
        }
        sort($gaps);
        return $gaps[intdiv(count($gaps), 2)];
    }
}

<?php
namespace AdmidioPlugin\DevTools\Tasks;

use RuntimeException;

/**
 ***********************************************************************************************
 * Runs the Admidio command line from the browser.
 *
 * A command runs as a separate process of the PHP command-line binary - exactly as if it had been
 * typed in a shell as the web server user - so the CLI's own access policy (cli-config.php), its
 * rights check and its log apply unchanged. It acts as the current user in the current
 * organization and with the host of the current request, unless the arguments say otherwise.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class CliRunner
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $configuredBinary = ''
    ) {
    }

    /**
     * The PHP command-line binary: the configured one, or one found next to this PHP.
     */
    public function getPhpBinary(): string
    {
        if ($this->configuredBinary !== '') {
            return $this->configuredBinary;
        }

        $candidates = array();
        if (PHP_BINARY !== '' && preg_match('/(^|[\/\\\\])php[0-9.]*(\.exe)?$/i', PHP_BINARY)) {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php');
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $directory) {
            if ($directory !== '') {
                $candidates[] = $directory . DIRECTORY_SEPARATOR . 'php';
            }
        }
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';

        foreach ($candidates as $candidate) {
            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException('No PHP command-line binary was found. Set its path in the settings of the DevTools plugin.');
    }

    /**
     * Split a command line into arguments: blanks separate, single or double quotes group.
     * @return array<int,string>
     */
    public static function splitArguments(string $line): array
    {
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\'([^\']*)\'|(\S+)/', $line, $matches, PREG_SET_ORDER);
        $arguments = array();
        foreach ($matches as $match) {
            if (isset($match[3]) && $match[3] !== '') {
                $arguments[] = $match[3];
            } elseif (isset($match[2]) && $match[2] !== '') {
                $arguments[] = $match[2];
            } else {
                $arguments[] = stripcslashes($match[1]);
            }
        }
        return $arguments;
    }

    /**
     * Run the command line.
     * @param array<int,string> $arguments Command and arguments, without the admidio script.
     * @param array<string,string> $context Global options (as, organization, host) that are added
     *                                      unless the arguments contain them already.
     * @return array{command:string, exitCode:int, output:string, errors:string, duration:float}
     */
    public function run(array $arguments, array $context = array(), int $timeout = 600): array
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('PHP may not start other programs here (proc_open is disabled).');
        }

        foreach ($context as $name => $value) {
            if ($value === '') {
                continue;
            }
            $given = false;
            foreach ($arguments as $argument) {
                if ($argument === '--' . $name || str_starts_with($argument, '--' . $name . '=')) {
                    $given = true;
                }
            }
            if (!$given) {
                $arguments[] = '--' . $name . '=' . $value;
            }
        }
        if (!in_array('--no-interaction', $arguments, true)) {
            $arguments[] = '--no-interaction';
        }

        $command = array_merge(array($this->getPhpBinary(), $this->rootPath . '/admidio'), $arguments);
        $outputFile = (string)tempnam(sys_get_temp_dir(), 'devtools');
        $errorFile = (string)tempnam(sys_get_temp_dir(), 'devtools');

        $environment = getenv();
        $environment['XDEBUG_MODE'] = 'off';
        unset($environment['ADMIDIO_NO_DEFAULTS']);

        @set_time_limit($timeout + 30);
        $start = microtime(true);
        $process = proc_open($command, array(
            0 => array('file', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null', 'r'),
            1 => array('file', $outputFile, 'w'),
            2 => array('file', $errorFile, 'w')
        ), $pipes, $this->rootPath, $environment);

        if (!is_resource($process)) {
            throw new RuntimeException('The command line could not be started.');
        }

        $exitCode = -1;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = (int)$status['exitcode'];
                break;
            }
            if (microtime(true) - $start > $timeout) {
                proc_terminate($process);
                $exitCode = -1;
                break;
            }
            usleep(50000);
        }
        proc_close($process);

        $result = array(
            'command' => implode(' ', array_map(array(self::class, 'quoteForDisplay'), array_merge(array('php', 'admidio'), $arguments))),
            'exitCode' => $exitCode,
            'output' => (string)file_get_contents($outputFile),
            'errors' => (string)file_get_contents($errorFile),
            'duration' => round(microtime(true) - $start, 2)
        );
        @unlink($outputFile);
        @unlink($errorFile);
        return $result;
    }

    /**
     * All commands of the command line, from "help --all --format=json".
     * @param array<string,string> $context
     * @return array<string,array<string,mixed>>
     */
    public function listCommands(array $context): array
    {
        $result = $this->run(array('help', '--all', '--format=json'), array('host' => $context['host'] ?? ''));
        $commands = json_decode($result['output'], true);
        if (!is_array($commands)) {
            throw new RuntimeException(trim($result['errors'] . "\n" . $result['output']));
        }
        return $commands;
    }

    /**
     * The maintenance jobs, from "maintenance:jobs --format=json".
     * @param array<string,string> $context
     * @return array<int,array<string,mixed>>
     */
    public function listMaintenanceJobs(array $context): array
    {
        $result = $this->run(array('maintenance:jobs', '--format=json'), $context);
        $jobs = json_decode($result['output'], true);
        if (!is_array($jobs)) {
            throw new RuntimeException(trim($result['errors'] . "\n" . $result['output']));
        }
        return array_values($jobs);
    }

    private static function quoteForDisplay(string $argument): string
    {
        return preg_match('/^[A-Za-z0-9_\-=:.,\/@%+]+$/', $argument) ? $argument : escapeshellarg($argument);
    }
}

<?php
namespace AdmidioPlugin\DevTools\Logs;

use SplFileObject;

/**
 ***********************************************************************************************
 * Reads the log files of the installation: the files in adm_my_files/logs (Admidio, CLI and core
 * update logs) and the PHP error log, if PHP writes it into a readable file.
 *
 * Only the end of a file is read, so a log of hundreds of megabytes costs no more than a small one.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class LogReader
{
    public const PHP_ERROR_LOG = 'php-error-log';
    public const LEVELS = array('DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY');

    private const READ_BYTES = 4 * 1024 * 1024;

    public function __construct(private readonly string $logDirectory)
    {
    }

    /**
     * The log files, newest first: id => array(name, size, mtime).
     * @return array<string,array{name:string, size:int, mtime:int}>
     */
    public function getFiles(): array
    {
        $files = array();
        foreach (glob($this->logDirectory . '/*.log') ?: array() as $file) {
            $files[basename($file)] = array('name' => basename($file), 'size' => (int)filesize($file), 'mtime' => (int)filemtime($file));
        }
        uasort($files, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $phpLog = $this->getPhpErrorLog();
        if ($phpLog !== null) {
            $files[self::PHP_ERROR_LOG] = array('name' => 'PHP: ' . $phpLog, 'size' => (int)filesize($phpLog), 'mtime' => (int)filemtime($phpLog));
        }
        return $files;
    }

    /**
     * The PHP error log, if it is a readable file (not syslog, not stderr).
     */
    public function getPhpErrorLog(): ?string
    {
        $file = (string)ini_get('error_log');
        if ($file === '' || $file === 'syslog' || str_starts_with($file, '/dev/') || !is_file($file) || !is_readable($file)) {
            return null;
        }
        return $file;
    }

    /**
     * The path of a log file chosen by its id, or null if there is no such file.
     */
    public function getPath(string $id): ?string
    {
        if ($id === self::PHP_ERROR_LOG) {
            return $this->getPhpErrorLog();
        }
        if (preg_match('/^[A-Za-z0-9._-]+\.log$/', $id) !== 1) {
            return null;
        }
        $file = $this->logDirectory . '/' . $id;
        return is_file($file) ? $file : null;
    }

    /**
     * The last entries of a log, newest first. A line that does not start with a time stamp belongs
     * to the entry before it (stack traces, wrapped messages).
     * @param string $minimumLevel Only entries of this level or higher; empty for all.
     * @param string $search Only entries that contain this text (case-insensitive).
     * @return array<int,array{time:string, level:string, text:string}>
     */
    public function read(string $file, int $limit = 200, string $minimumLevel = '', string $search = ''): array
    {
        $size = (int)filesize($file);
        $handle = new SplFileObject($file, 'rb');
        $offset = max(0, $size - self::READ_BYTES);
        $handle->fseek($offset);
        $content = (string)$handle->fread(max(1, $size - $offset));
        if ($offset > 0) {
            // the first line is most likely cut
            $content = (string)substr($content, (int)strpos($content, "\n") + 1);
        }

        $entries = array();
        $current = null;
        foreach (preg_split('/\r?\n/', $content) ?: array() as $line) {
            if (preg_match('/^\[?(\d{4}-\d{2}-\d{2}[ T][\d:.]+)[^\]]*\]?\s*(?:[\w-]+\.)?(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)?:?\s?(.*)$/', $line, $match)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = array('time' => $match[1], 'level' => $match[2] !== '' ? $match[2] : self::guessLevel($match[3]), 'text' => $match[3]);
            } elseif (preg_match('/^\[(\d{2}-\w{3}-\d{4} [\d:]+)[^\]]*\]\s*(.*)$/', $line, $match)) {
                // the format of the PHP error log
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = array('time' => $match[1], 'level' => self::guessLevel($match[2]), 'text' => $match[2]);
            } elseif ($current !== null) {
                $current['text'] .= "\n" . $line;
            } elseif (trim($line) !== '') {
                $current = array('time' => '', 'level' => self::guessLevel($line), 'text' => $line);
            }
        }
        if ($current !== null) {
            $entries[] = $current;
        }

        $minimum = $minimumLevel === '' ? 0 : (int)array_search($minimumLevel, self::LEVELS, true);
        $result = array();
        for ($index = count($entries) - 1; $index >= 0 && count($result) < $limit; --$index) {
            $entry = $entries[$index];
            $entry['text'] = rtrim($entry['text']);
            $level = array_search($entry['level'], self::LEVELS, true);
            if ($level !== false && $level < $minimum) {
                continue;
            }
            if ($search !== '' && stripos($entry['text'], $search) === false) {
                continue;
            }
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * The level of a line of the PHP error log ("PHP Warning: …").
     */
    private static function guessLevel(string $text): string
    {
        if (preg_match('/PHP (Fatal error|Parse error)/i', $text)) {
            return 'CRITICAL';
        }
        if (preg_match('/PHP (Warning|Deprecated|Notice)/i', $text, $match)) {
            return match (strtolower($match[1])) {
                'warning' => 'WARNING',
                'deprecated' => 'NOTICE',
                default => 'NOTICE'
            };
        }
        return 'INFO';
    }
}

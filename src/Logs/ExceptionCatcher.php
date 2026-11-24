<?php
namespace AdmidioPlugin\DevTools\Logs;

use AdmidioPlugin\DevTools\DevTools;
use Throwable;

/**
 ***********************************************************************************************
 * Keeps every exception that ends a request, with enough context to analyse it later: message,
 * class, place, stack trace, URL, method, user and the names of the request parameters. Values of
 * parameters are not stored, except that it is noted whether one was set; the values of password-,
 * token- and secret-like parameters would be the worst to keep.
 *
 * One JSON file per exception in adm_my_files/devtools/exceptions/; the newest MAX_ENTRIES are kept.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class ExceptionCatcher
{
    public const MAX_ENTRIES = 200;

    /**
     * Callback of **exception_terminating**.
     */
    public static function store(Throwable $exception, bool $jsonResponse = false): void
    {
        global $gCurrentUserId;

        try {
            $directory = DevTools::dataPath('exceptions');
            $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));

            $chain = array();
            for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
                $chain[] = array(
                    'class' => get_class($current),
                    'message' => $current->getMessage(),
                    'code' => (string)$current->getCode(),
                    'file' => $current->getFile(),
                    'line' => $current->getLine(),
                    'trace' => $current->getTraceAsString()
                );
            }

            $entry = array(
                'id' => $id,
                'time' => date('c'),
                'method' => (string)($_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : '')),
                'url' => defined('CURRENT_URL') ? CURRENT_URL : (string)($_SERVER['REQUEST_URI'] ?? ''),
                'user' => (int)$gCurrentUserId,
                'json' => $jsonResponse,
                'get' => array_keys($_GET),
                'post' => array_keys($_POST),
                'exceptions' => $chain
            );

            file_put_contents($directory . '/' . $id . '.json', json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

            $files = glob($directory . '/*.json') ?: array();
            rsort($files, SORT_STRING);
            foreach (array_slice($files, self::MAX_ENTRIES) as $old) {
                @unlink($old);
            }
        } catch (Throwable) {
            // the original exception is the one that matters
        }
    }

    /**
     * The stored exceptions, newest first.
     * @return array<int,array<string,mixed>>
     */
    public static function list(): array
    {
        $entries = array();
        $files = glob(DevTools::dataPath('exceptions') . '/*.json') ?: array();
        rsort($files, SORT_STRING);
        foreach ($files as $file) {
            $entry = json_decode((string)file_get_contents($file), true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function read(string $id): ?array
    {
        if (preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $id) !== 1) {
            return null;
        }
        $file = DevTools::dataPath('exceptions') . '/' . $id . '.json';
        $entry = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        return is_array($entry) ? $entry : null;
    }

    public static function deleteAll(): void
    {
        foreach (glob(DevTools::dataPath('exceptions') . '/*.json') ?: array() as $file) {
            @unlink($file);
        }
    }
}

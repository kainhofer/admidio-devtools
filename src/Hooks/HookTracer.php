<?php
namespace AdmidioPlugin\DevTools\Hooks;

use AdmidioPlugin\DevTools\DevTools;
use Throwable;

/**
 ***********************************************************************************************
 * Records which hook callbacks a request ran, in which order and how long each one took.
 *
 * After plugins_loaded the callback of every registration is replaced by a wrapper that measures
 * the call and then calls the original. Registration key, priority and order stay as they are, so
 * removing a registration by its ID or its callback works as before. The trace is written at the end
 * of the request into adm_my_files/devtools/traces/; the last MAX_TRACES requests are kept.
 *
 * Not visible this way: hooks that are dispatched without any callback, and callbacks that are
 * registered after plugins_loaded. That needs a tracer interface in the core (Hooks::setTracer()).
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class HookTracer
{
    public const MAX_TRACES = 50;
    public const MAX_CALLS = 5000;

    private static bool $installed = false;
    /** @var array<int,array<string,mixed>> */
    private static array $calls = array();
    private static int $depth = 0;
    private static int $dropped = 0;
    private static float $start = 0.0;

    /**
     * Callback of **plugins_loaded**: wrap every registered callback. The pages of DevTools itself
     * are not traced, so that looking at the traces does not push them out.
     */
    public static function install(): void
    {
        if (self::$installed || !HookRegistry::isAvailable()) {
            return;
        }
        $script = str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if (str_contains($script, '/plugins/' . DevTools::ID . '/') || str_contains($script, '/modules/' . DevTools::ID . '/')) {
            return;
        }

        self::$installed = true;
        self::$start = defined('SCRIPT_START_TIME') ? (float)SCRIPT_START_TIME : microtime(true);

        $raw = HookRegistry::getRaw();
        foreach ($raw as $type => $hooks) {
            foreach ($hooks as $name => $byPriority) {
                foreach ($byPriority as $priority => $registrations) {
                    foreach ($registrations as $key => $registration) {
                        if (isset($registration['originalCallback'])) {
                            continue;
                        }
                        $original = $registration['callback'];
                        $raw[$type][$name][$priority][$key]['originalCallback'] = $original;
                        $raw[$type][$name][$priority][$key]['callback'] = static function (mixed ...$args) use ($type, $name, $key, $original): mixed {
                            return HookTracer::call((string)$type, (string)$name, (string)$key, $original, $args);
                        };
                    }
                }
            }
        }
        HookRegistry::setRaw($raw);

        register_shutdown_function(array(self::class, 'write'));
    }

    /**
     * Call the original callback and record the call.
     * @param array<int,mixed> $args
     */
    public static function call(string $type, string $name, string $key, callable $original, array $args): mixed
    {
        $begin = hrtime(true);
        $entry = array(
            'time' => round((microtime(true) - self::$start) * 1000, 2),
            'depth' => self::$depth,
            'type' => $type,
            'hook' => $name,
            'key' => $key,
            'callback' => HookRegistry::describeCallback($original)['name'],
            'args' => self::summarizeList($args)
        );

        // the slot is taken now, so that the calls are listed in the order in which they started
        $index = null;
        if (count(self::$calls) < self::MAX_CALLS) {
            $index = count(self::$calls);
            self::$calls[] = $entry;
        } else {
            ++self::$dropped;
        }

        ++self::$depth;
        try {
            $result = $original(...$args);
            if ($type === 'filter') {
                $entry['changed'] = !array_key_exists(0, $args) || $result !== $args[0];
                if ($entry['changed']) {
                    $entry['before'] = array_key_exists(0, $args) ? self::summarize($args[0]) : '';
                    $entry['after'] = self::summarize($result);
                }
            } elseif ($type === 'resolver') {
                $entry['answer'] = $result === null ? null : self::summarize($result);
            }
            return $result;
        } catch (Throwable $exception) {
            $entry['exception'] = get_class($exception) . ': ' . $exception->getMessage();
            throw $exception;
        } finally {
            --self::$depth;
            $entry['duration'] = round((hrtime(true) - $begin) / 1e6, 3);
            if ($index !== null) {
                self::$calls[$index] = $entry;
            }
        }
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>
     */
    private static function summarizeList(array $values): array
    {
        return array_map(array(self::class, 'summarize'), array_values($values));
    }

    /**
     * A short, readable description of a value. Strings are cut, objects and arrays only described.
     */
    public static function summarize(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_string($value)) {
            $short = mb_strlen($value) > 80 ? mb_substr($value, 0, 80) . '…' : $value;
            return '"' . $short . '"';
        }
        if (is_array($value)) {
            $keys = array_slice(array_keys($value), 0, 6);
            return 'array(' . count($value) . ')' . ($keys !== array() ? ' [' . implode(', ', $keys) . (count($value) > 6 ? ', …' : '') . ']' : '');
        }
        return get_debug_type($value);
    }

    /**
     * Shutdown function: write the trace of this request and drop the oldest ones.
     */
    public static function write(): void
    {
        global $gCurrentUserId;

        if (self::$calls === array()) {
            return;
        }

        try {
            $directory = DevTools::dataPath('traces');
            $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $trace = array(
                'id' => $id,
                'time' => date('c'),
                'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
                'url' => defined('CURRENT_URL') ? CURRENT_URL : (string)($_SERVER['REQUEST_URI'] ?? ''),
                'user' => (int)$gCurrentUserId,
                'duration' => round((microtime(true) - self::$start) * 1000, 1),
                'dropped' => self::$dropped,
                'calls' => self::$calls
            );
            file_put_contents($directory . '/' . $id . '.json', json_encode($trace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

            $files = glob($directory . '/*.json') ?: array();
            rsort($files, SORT_STRING);
            foreach (array_slice($files, self::MAX_TRACES) as $old) {
                @unlink($old);
            }
        } catch (Throwable) {
            // a trace that cannot be written must not disturb the request
        }
    }

    /**
     * The stored traces, newest first, without their calls.
     * @return array<int,array<string,mixed>>
     */
    public static function listTraces(): array
    {
        $traces = array();
        $files = glob(DevTools::dataPath('traces') . '/*.json') ?: array();
        rsort($files, SORT_STRING);
        foreach ($files as $file) {
            $trace = json_decode((string)file_get_contents($file), true);
            if (is_array($trace)) {
                $trace['count'] = count($trace['calls'] ?? array());
                unset($trace['calls']);
                $traces[] = $trace;
            }
        }
        return $traces;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function readTrace(string $id): ?array
    {
        if (preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $id) !== 1) {
            return null;
        }
        $file = DevTools::dataPath('traces') . '/' . $id . '.json';
        $trace = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        return is_array($trace) ? $trace : null;
    }

    public static function deleteAll(): void
    {
        foreach (glob(DevTools::dataPath('traces') . '/*.json') ?: array() as $file) {
            @unlink($file);
        }
    }
}

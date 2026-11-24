<?php
namespace AdmidioPlugin\DevTools\Hooks;

use Admidio\Hooks\Hooks;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 ***********************************************************************************************
 * Read (and for the tracer: rewrite) the registrations of Admidio's hook registry.
 *
 * The core offers no read access to the registry; this is the one place where the plugin reaches
 * into the private static property Hooks::$registrations by reflection. If the core gets a
 * Hooks::getRegistrations() some day, only this class changes.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class HookRegistry
{
    private static function property(): ReflectionProperty
    {
        return new ReflectionProperty(Hooks::class, 'registrations');
    }

    /**
     * The raw registry: $registrations[$type][$hookName][$priority][$key] = registration.
     * @return array<string,array<string,array<int,array<string,array<string,mixed>>>>>
     */
    public static function getRaw(): array
    {
        return self::property()->getValue();
    }

    /**
     * Replace the raw registry. Only the tracer does this, to wrap the callbacks.
     * @param array<string,array<string,array<int,array<string,array<string,mixed>>>>> $registrations
     */
    public static function setRaw(array $registrations): void
    {
        self::property()->setValue(null, $registrations);
    }

    /**
     * All registrations as flat rows, ordered by hook, kind and call order.
     * @return array<int,array<string,mixed>>
     */
    public static function getRegistrations(): array
    {
        $rows = array();

        foreach (self::getRaw() as $type => $hooks) {
            foreach ($hooks as $name => $byPriority) {
                ksort($byPriority, SORT_NUMERIC);
                $order = 0;
                foreach ($byPriority as $priority => $registrations) {
                    uasort($registrations, static fn(array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
                    foreach ($registrations as $registration) {
                        $callback = $registration['originalCallback'] ?? $registration['callback'];
                        $location = self::describeCallback($callback);
                        $rows[] = array(
                            'hook' => (string)$name,
                            'type' => (string)$type,
                            'priority' => (int)$priority,
                            'order' => ++$order,
                            'key' => (string)$registration['key'],
                            'acceptedArgs' => $registration['acceptedArgs'],
                            'callback' => $location['name'],
                            'file' => $location['file'],
                            'line' => $location['line'],
                            'owner' => self::ownerOfFile($location['file'])
                        );
                    }
                }
            }
        }

        usort($rows, static fn(array $a, array $b): int => array($a['hook'], $a['type'], $a['order']) <=> array($b['hook'], $b['type'], $b['order']));
        return $rows;
    }

    /**
     * A readable name and the source location of a callback.
     * @return array{name:string, file:string, line:int}
     */
    public static function describeCallback(mixed $callback): array
    {
        try {
            if ($callback instanceof Closure) {
                $reflection = new ReflectionFunction($callback);
                $scope = $reflection->getClosureScopeClass();
                $name = '{closure}' . ($scope !== null ? ' in ' . $scope->getName() : '');
                return array('name' => $name, 'file' => (string)$reflection->getFileName(), 'line' => (int)$reflection->getStartLine());
            }
            if (is_string($callback) && str_contains($callback, '::')) {
                $callback = explode('::', $callback, 2);
            }
            if (is_array($callback) && count($callback) === 2) {
                $class = is_object($callback[0]) ? get_class($callback[0]) : (string)$callback[0];
                $reflection = new ReflectionMethod($class, (string)$callback[1]);
                return array('name' => $class . '::' . $callback[1], 'file' => (string)$reflection->getFileName(), 'line' => (int)$reflection->getStartLine());
            }
            if (is_string($callback)) {
                $reflection = new ReflectionFunction($callback);
                return array('name' => $callback, 'file' => (string)$reflection->getFileName(), 'line' => (int)$reflection->getStartLine());
            }
            if (is_object($callback)) {
                $reflection = new ReflectionMethod($callback, '__invoke');
                return array('name' => get_class($callback) . '::__invoke', 'file' => (string)$reflection->getFileName(), 'line' => (int)$reflection->getStartLine());
            }
        } catch (Throwable) {
            // fall through to the unknown callback
        }

        return array('name' => get_debug_type($callback), 'file' => '', 'line' => 0);
    }

    /**
     * Who registered a callback: "core" or the ID of the plugin whose directory holds the file.
     */
    public static function ownerOfFile(string $file): string
    {
        if ($file === '') {
            return '?';
        }
        $file = str_replace('\\', '/', $file);
        $plugins = str_replace('\\', '/', ADMIDIO_PATH . FOLDER_PLUGINS) . '/';
        if (str_starts_with($file, $plugins)) {
            return explode('/', substr($file, strlen($plugins)), 2)[0];
        }
        return 'core';
    }

    /**
     * A path relative to the Admidio directory, for display.
     */
    public static function relativePath(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $root = str_replace('\\', '/', ADMIDIO_PATH) . '/';
        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }

    /**
     * Whether the reflection access to the registry works with this version of the core.
     */
    public static function isAvailable(): bool
    {
        try {
            $class = new ReflectionClass(Hooks::class);
            return $class->hasProperty('registrations') && is_array(self::getRaw());
        } catch (Throwable) {
            return false;
        }
    }
}

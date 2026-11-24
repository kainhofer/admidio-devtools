<?php
/**
 * Makes the plugin classes loadable in tests, which run without the plugin runtime that normally
 * registers the PSR-4 root of plugin.json.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'AdmidioPlugin\\DevTools\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

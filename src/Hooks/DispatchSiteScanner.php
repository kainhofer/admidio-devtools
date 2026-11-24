<?php
namespace AdmidioPlugin\DevTools\Hooks;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 ***********************************************************************************************
 * Finds the places in the code of the core and the plugins that dispatch a hook, and checks the
 * registrations against them.
 *
 * The scan is textual: Hooks::doAction|doActionCatchErrors|applyFilters|applyTypedFilters|resolve
 * with a literal hook name. A dispatch with a computed name is listed as dynamic; names that are
 * kept in a class constant (const HOOK = '…') count as dispatched. The persistence hooks of the
 * entities (entity_created, user_updated, …) are generated from the hook ID of each entity and are
 * recognised by their suffix.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class DispatchSiteScanner
{
    public const DIRECTORIES = array('src', 'modules', 'system', 'install', 'plugins', 'rss');

    public const ENTITY_SUFFIXES = array(
        'creating', 'created', 'create_failed', 'updating', 'updated', 'update_failed',
        'deleting', 'deleted', 'delete_failed', 'readable_name'
    );

    private const DISPATCH_PATTERN = '/Hooks::(doAction|doActionCatchErrors|applyFilters|applyTypedFilters|resolve)\(\s*([^,)]+)/';

    private const KIND = array(
        'doAction' => 'action',
        'doActionCatchErrors' => 'action',
        'applyFilters' => 'filter',
        'applyTypedFilters' => 'filter',
        'resolve' => 'resolver'
    );

    /** @var array<int,array<string,mixed>>|null */
    private ?array $sites = null;

    /** @var array<string,string> hook names found in constants => file */
    private array $constantNames = array();

    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * All dispatch sites: hook (or the expression of a dynamic name), kind, method, file, line, owner.
     * @return array<int,array<string,mixed>>
     */
    public function getSites(): array
    {
        if ($this->sites !== null) {
            return $this->sites;
        }

        $this->sites = array();
        foreach (self::DIRECTORIES as $directory) {
            $path = $this->rootPath . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                $pathname = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() !== 'php' || preg_match('#/(vendor|tests|\.git|node_modules)/#', $pathname)) {
                    continue;
                }
                $this->scanFile($pathname);
            }
        }

        usort($this->sites, static fn(array $a, array $b): int => array($a['hook'], $a['file'], $a['line']) <=> array($b['hook'], $b['file'], $b['line']));
        return $this->sites;
    }

    private function scanFile(string $file): void
    {
        $content = (string)file_get_contents($file);
        if (!str_contains($content, 'Hooks::') && !str_contains($content, 'HOOK')) {
            return;
        }

        if (preg_match_all('/const\s+[A-Z_]*HOOK[A-Z_]*\s*=\s*\'([a-z0-9_\-]+)\'/', $content, $constants)) {
            foreach ($constants[1] as $name) {
                $this->constantNames[$name] = $file;
            }
        }

        if (!preg_match_all(self::DISPATCH_PATTERN, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $relative = HookRegistry::relativePath($file);
        foreach ($matches[0] as $index => $match) {
            $argument = trim($matches[2][$index][0]);
            $literal = preg_match('/^\'([a-z0-9_\-]+)\'$/', $argument, $name) === 1;
            $this->sites[] = array(
                'hook' => $literal ? $name[1] : $argument,
                'dynamic' => !$literal,
                'kind' => self::KIND[$matches[1][$index][0]],
                'method' => $matches[1][$index][0],
                'file' => $relative,
                'line' => substr_count($content, "\n", 0, $match[1]) + 1,
                'owner' => HookRegistry::ownerOfFile($file)
            );
        }
    }

    public static function isEntityHook(string $name): bool
    {
        foreach (self::ENTITY_SUFFIXES as $suffix) {
            if (str_ends_with($name, '_' . $suffix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Problems that the registrations and the dispatch sites show together.
     * @param array<int,array<string,mixed>> $registrations From HookRegistry::getRegistrations().
     * @return array<int,array{level:string, key:string, params:array<int,string>}>
     */
    public function getWarnings(array $registrations): array
    {
        $dispatched = array();
        foreach ($this->getSites() as $site) {
            if (!$site['dynamic']) {
                $dispatched[$site['hook']] = true;
            }
        }
        foreach (array_keys($this->constantNames) as $name) {
            $dispatched[$name] = true;
        }

        $warnings = array();
        $seen = array();
        $filterOwners = array();

        foreach ($registrations as $registration) {
            $hook = $registration['hook'];
            if (!isset($dispatched[$hook]) && !self::isEntityHook($hook) && !isset($seen[$hook])) {
                $seen[$hook] = true;
                $warnings[] = array('level' => 'warning', 'key' => 'PLG_DEVTOOLS_HOOK_WARNING_NOT_DISPATCHED',
                    'params' => array($hook, $registration['owner']));
            }
            if ($registration['type'] === 'filter') {
                $filterOwners[$hook][$registration['owner']] = true;
            }
        }

        foreach ($filterOwners as $hook => $owners) {
            if (count($owners) > 1) {
                $warnings[] = array('level' => 'info', 'key' => 'PLG_DEVTOOLS_HOOK_WARNING_SHARED_FILTER',
                    'params' => array((string)$hook, implode(', ', array_keys($owners))));
            }
        }

        foreach ($this->getSites() as $site) {
            if ($site['dynamic'] || $site['owner'] === 'core' || $site['owner'] === '?') {
                continue;
            }
            $prefixes = array($site['owner'], str_replace('-', '_', $site['owner']));
            $prefixed = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($site['hook'], $prefix . '_') || str_starts_with($site['hook'], $prefix . '-')) {
                    $prefixed = true;
                }
            }
            if (!$prefixed && !isset($seen['prefix:' . $site['hook']])) {
                $seen['prefix:' . $site['hook']] = true;
                $warnings[] = array('level' => 'warning', 'key' => 'PLG_DEVTOOLS_HOOK_WARNING_NO_PREFIX',
                    'params' => array($site['hook'], $site['owner'], $site['file'] . ':' . $site['line']));
            }
        }

        return $warnings;
    }
}

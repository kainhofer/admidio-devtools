<?php
/**
 ***********************************************************************************************
 * DevTools - hook inspector: the registered callbacks, the places that dispatch hooks, and the
 * problems both show together.
 *
 * Parameters:
 * view : registrations (default), sites, warnings
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Hooks\DispatchSiteScanner;
use AdmidioPlugin\DevTools\Hooks\HookRegistry;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getView = admFuncVariableIsValid($_GET, 'view', 'string', array('validValues' => array('registrations', 'sites', 'warnings'), 'defaultValue' => 'registrations'));

    $available = HookRegistry::isAvailable();
    $registrations = $available ? HookRegistry::getRegistrations() : array();
    $scanner = new DispatchSiteScanner(ADMIDIO_PATH);
    $sites = $scanner->getSites();

    // one group per hook: its callbacks in call order and the places that dispatch it
    $hooks = array();
    foreach ($registrations as $registration) {
        $registration['location'] = $registration['file'] === '' ? '' : HookRegistry::relativePath($registration['file']) . ':' . $registration['line'];
        $hooks[$registration['hook']]['callbacks'][] = $registration;
    }
    foreach ($sites as $site) {
        $hooks[$site['hook']]['sites'][] = $site;
    }
    foreach ($hooks as $name => &$hook) {
        $hook['name'] = (string)$name;
        $hook['callbacks'] = $hook['callbacks'] ?? array();
        $hook['sites'] = $hook['sites'] ?? array();
        $hook['kinds'] = array_values(array_unique(array_merge(array_column($hook['callbacks'], 'type'), array_column($hook['sites'], 'kind'))));
        $hook['owners'] = array_values(array_unique(array_column($hook['callbacks'], 'owner')));
        $hook['dynamic'] = $hook['sites'] !== array() && !in_array(false, array_column($hook['sites'], 'dynamic'), true);
        $hook['entity'] = DispatchSiteScanner::isEntityHook((string)$name);
    }
    unset($hook);
    ksort($hooks, SORT_STRING);

    // the registrations view shows the hooks with callbacks, the sites view those that are dispatched
    $hooks = array_values(array_filter($hooks, static fn(array $hook): bool => $getView === 'registrations'
        ? $hook['callbacks'] !== array() : $hook['sites'] !== array()));

    $warnings = array();
    if ($getView === 'warnings') {
        foreach ($scanner->getWarnings($registrations) as $warning) {
            $warnings[] = array('level' => $warning['level'], 'text' => $gL10n->get($warning['key'], $warning['params']));
        }
    }

    $views = array();
    foreach (array('registrations' => 'PLG_DEVTOOLS_HOOK_REGISTRATIONS', 'sites' => 'PLG_DEVTOOLS_HOOK_SITES', 'warnings' => 'PLG_DEVTOOLS_HOOK_WARNINGS') as $view => $label) {
        $views[] = array('url' => DevTools::url('hooks.php', array('view' => $view)), 'label' => $gL10n->get($label), 'active' => $view === $getView);
    }

    $page = DevTools::createPage('hooks.php', 'plugin.devtools.hooks.tpl');
    $page->assignSmartyVariable('available', $available);
    $page->assignSmartyVariable('view', $getView);
    $page->assignSmartyVariable('views', $views);
    $page->assignSmartyVariable('hooks', $hooks);
    $page->assignSmartyVariable('callbackCount', count($registrations));
    $page->assignSmartyVariable('warnings', $warnings);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

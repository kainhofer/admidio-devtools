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
    foreach ($registrations as &$registration) {
        $registration['file'] = $registration['file'] === '' ? '' : HookRegistry::relativePath($registration['file']) . ':' . $registration['line'];
    }
    unset($registration);

    $scanner = new DispatchSiteScanner(ADMIDIO_PATH);
    $sites = $getView === 'registrations' ? array() : $scanner->getSites();
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
    $page->assignSmartyVariable('registrations', $registrations);
    $page->assignSmartyVariable('sites', $sites);
    $page->assignSmartyVariable('warnings', $warnings);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

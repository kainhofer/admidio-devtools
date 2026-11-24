<?php
/**
 ***********************************************************************************************
 * DevTools - hook traces of the last requests.
 *
 * Parameters:
 * id   : show the trace with this id
 * mode : delete_all (POST)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Hooks\HookTracer;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getId = admFuncVariableIsValid($_GET, 'id', 'string');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('show', 'delete_all'), 'defaultValue' => 'show'));

    if ($getMode === 'delete_all') {
        DevTools::requirePost();
        HookTracer::deleteAll();
        admRedirect(DevTools::url('traces.php'));
    }

    $trace = $getId !== '' ? HookTracer::readTrace($getId) : null;
    $traces = array();
    if ($trace === null) {
        foreach (HookTracer::listTraces() as $entry) {
            $entry['url'] = (string)$entry['url'];
            $entry['link'] = DevTools::url('traces.php', array('id' => $entry['id']));
            $traces[] = $entry;
        }
    } else {
        $byHook = array();
        foreach ($trace['calls'] as $call) {
            $byHook[$call['hook']] = ($byHook[$call['hook']] ?? 0) + ($call['duration'] ?? 0);
        }
        arsort($byHook);
        $trace['slowest'] = array_slice($byHook, 0, 10, true);
    }

    $page = DevTools::createPage('traces.php', 'plugin.devtools.traces.tpl');
    $page->assignSmartyVariable('enabled', $gSettingsManager->getBool('devtools_hook_trace'));
    $page->assignSmartyVariable('trace', $trace);
    $page->assignSmartyVariable('traces', $traces);
    $page->assignSmartyVariable('listUrl', DevTools::url('traces.php'));
    $page->assignSmartyVariable('deleteUrl', DevTools::url('traces.php', array('mode' => 'delete_all')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

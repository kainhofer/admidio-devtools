<?php
/**
 ***********************************************************************************************
 * DevTools - overview.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Hooks\HookTracer;
use AdmidioPlugin\DevTools\Logs\ExceptionCatcher;
use AdmidioPlugin\DevTools\Mail\MailCatcher;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $features = array();
    foreach (DevTools::PAGES as $file => $definition) {
        if ($file !== 'index.php') {
            $features[] = array(
                'url' => DevTools::url($file),
                'label' => $gL10n->get($definition[0]),
                'description' => $gL10n->get($definition[0] . '_DESC'),
                'icon' => $definition[1]
            );
        }
    }

    $store = DevTools::getSnapshotStore();
    $snapshots = $store->list();

    $page = DevTools::createPage('index.php', 'plugin.devtools.index.tpl');
    $page->assignSmartyVariable('features', $features);
    $page->assignSmartyVariable('status', array(
        'snapshotPath' => $store->getConfig()->snapshotPath,
        'snapshotCount' => count($snapshots),
        'lastSnapshot' => $snapshots[0]['name'] ?? '',
        'mailMode' => $gSettingsManager->getString('devtools_mail_mode'),
        'mailCount' => count(MailCatcher::list()),
        'exceptionCount' => count(ExceptionCatcher::list()),
        'hookTrace' => $gSettingsManager->getBool('devtools_hook_trace'),
        'traceCount' => count(HookTracer::listTraces())
    ));
    $page->assignSmartyVariable('preferencesUrl', ADMIDIO_URL . FOLDER_MODULES . '/preferences.php?panel=' . DevTools::ID);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

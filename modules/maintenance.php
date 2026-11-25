<?php
/**
 ***********************************************************************************************
 * DevTools - the maintenance jobs: what they are, when they ran, whether cron still runs them, and
 * running them now.
 *
 * Parameters:
 * mode : list (default), run (POST, with job = name, or empty for all jobs)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Tasks\CliRunner;
use AdmidioPlugin\DevTools\Tasks\MaintenanceLog;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('list', 'run'), 'defaultValue' => 'list'));

    $runner = new CliRunner(ADMIDIO_PATH, trim($gSettingsManager->getString('devtools_php_binary')));
    $context = array(
        'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
        'organization' => (string)$gCurrentOrganization->getValue('org_shortname'),
        'as' => (string)$gCurrentUser->getValue('usr_login_name')
    );
    $errors = array();
    $result = null;

    if ($getMode === 'run') {
        DevTools::requirePost();
        $job = (string)($_POST['job'] ?? '');
        $arguments = array('maintenance:run', '--yes', '--format=table');
        if ($job !== '') {
            $arguments[] = '--job=' . $job;
        }
        try {
            $result = $runner->run($arguments, $context);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $jobs = array();
    try {
        $jobs = $runner->listMaintenanceJobs($context);
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }

    $log = new MaintenanceLog(ADMIDIO_PATH . FOLDER_DATA . '/logs');
    $now = time();
    $lastRuns = $log->getLastRunPerJob(array_column($jobs, 'job'));
    foreach ($jobs as &$job) {
        $job['lastRun'] = $lastRuns[$job['job']] ?? null;
    }
    unset($job);

    $organizations = array();
    foreach ($log->getOrganizations($now) as $organization) {
        $organization['intervalText'] = $organization['interval'] === null ? '' : DevTools::formatDuration($organization['interval']);
        $organization['ageText'] = DevTools::formatDuration($now - $organization['last']['timestamp']);
        $organizations[] = $organization;
    }

    $page = DevTools::createPage('maintenance.php', 'plugin.devtools.maintenance.tpl');
    $page->assignSmartyVariable('jobs', $jobs);
    $page->assignSmartyVariable('organizations', $organizations);
    $page->assignSmartyVariable('recentRuns', array_reverse(array_slice($log->getRuns(), -25)));
    $page->assignSmartyVariable('logDays', MaintenanceLog::DAYS);
    $page->assignSmartyVariable('result', $result);
    $page->assignSmartyVariable('errors', $errors);
    $page->assignSmartyVariable('currentOrganization', $context['organization']);
    $page->assignSmartyVariable('runUrl', DevTools::url('maintenance.php', array('mode' => 'run')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

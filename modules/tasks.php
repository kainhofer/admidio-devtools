<?php
/**
 ***********************************************************************************************
 * DevTools - the commands and maintenance jobs of the command line, and running them.
 *
 * Parameters:
 * mode    : run, run_job (POST)
 * command : the command line to run (without "php admidio")
 * job     : the maintenance job to run
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Tasks\CliRunner;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('list', 'run', 'run_job'), 'defaultValue' => 'list'));

    $runner = new CliRunner(ADMIDIO_PATH, trim($gSettingsManager->getString('devtools_php_binary')));
    $context = array(
        'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
        'organization' => (string)$gCurrentOrganization->getValue('org_shortname'),
        'as' => (string)$gCurrentUser->getValue('usr_login_name')
    );

    $result = null;
    $commandLine = '';
    $errors = array();
    $commands = array();
    $jobs = array();
    $tasks = array();

    try {
        $tasks = $runner->listCommands($context);
        foreach ($tasks as $name => $task) {
            if (!empty($task['aliasOf'])) {
                continue;
            }
            $commands[] = array(
                'name' => (string)$name,
                'component' => (string)($task['component'] ?? ''),
                'description' => (string)($task['description'] ?? ''),
                'usage' => (string)($task['usage'] ?? $name),
                'dryRun' => !empty($task['supportsDryRun']),
                'unavailable' => (string)($task['unavailableReason'] ?? '')
            );
        }
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }

    if ($getMode === 'run' || $getMode === 'run_job') {
        DevTools::requirePost();
        if ($getMode === 'run') {
            $commandLine = trim((string)($_POST['command'] ?? ''));
            $arguments = CliRunner::splitArguments($commandLine);
        } else {
            $arguments = array('maintenance:run', '--job=' . (string)($_POST['job'] ?? ''), '--yes');
            $commandLine = implode(' ', $arguments);
        }
        if ($arguments !== array()) {
            // --as is refused by the commands that do not act as a user
            $runContext = $context;
            $task = $tasks[$arguments[0]] ?? null;
            if ($task !== null && !empty($task['aliasOf'])) {
                $task = $tasks[$task['aliasOf']] ?? $task;
            }
            if ($task === null || empty($task['actorRequired'])) {
                unset($runContext['as']);
            }
            try {
                $result = $runner->run($arguments, $runContext);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }

    try {
        $jobs = $runner->listMaintenanceJobs($context);
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }

    $page = DevTools::createPage('tasks.php', 'plugin.devtools.tasks.tpl');
    $page->assignSmartyVariable('commands', $commands);
    $page->assignSmartyVariable('jobs', $jobs);
    $page->assignSmartyVariable('result', $result);
    $page->assignSmartyVariable('commandLine', $commandLine);
    $page->assignSmartyVariable('errors', $errors);
    $page->assignSmartyVariable('tasksDescription', $gL10n->get('PLG_DEVTOOLS_TASKS_DESC') . ' ' . $gL10n->get('PLG_DEVTOOLS_TASKS_ACTING', array($context['as'], $context['organization'])));
    $page->assignSmartyVariable('runUrl', DevTools::url('tasks.php', array('mode' => 'run')));
    $page->assignSmartyVariable('runJobUrl', DevTools::url('tasks.php', array('mode' => 'run_job')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

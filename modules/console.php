<?php
/**
 ***********************************************************************************************
 * DevTools - the Admidio command line in the browser, with the documentation of every command,
 * grouped by area.
 *
 * Parameters:
 * mode    : list (default), run (POST)
 * command : the command line to run (without "php admidio")
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

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('list', 'run'), 'defaultValue' => 'list'));

    $runner = new CliRunner(ADMIDIO_PATH, trim($gSettingsManager->getString('devtools_php_binary')));
    $context = array(
        'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
        'organization' => (string)$gCurrentOrganization->getValue('org_shortname'),
        'as' => (string)$gCurrentUser->getValue('usr_login_name')
    );

    $result = null;
    $commandLine = '';
    $errors = array();
    $tasks = array();

    try {
        $tasks = $runner->listCommands($context);
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }

    if ($getMode === 'run') {
        DevTools::requirePost();
        $commandLine = trim((string)($_POST['command'] ?? ''));
        $arguments = CliRunner::splitArguments($commandLine);
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

    // the documentation, grouped by the area before the colon (user:list -> user)
    $areas = array();
    foreach ($tasks as $name => $task) {
        if (!empty($task['aliasOf'])) {
            continue;
        }
        $area = str_contains((string)$name, ':') ? explode(':', (string)$name, 2)[0] : (string)$name;
        $areas[$area][] = array(
            'name' => (string)$name,
            'description' => (string)($task['description'] ?? ''),
            'usage' => (string)($task['usage'] ?? $name),
            'component' => (string)($task['component'] ?? ''),
            'arguments' => is_array($task['arguments'] ?? null) ? $task['arguments'] : array(),
            'options' => is_array($task['options'] ?? null) ? $task['options'] : array(),
            'examples' => is_array($task['examples'] ?? null) ? $task['examples'] : array(),
            'dryRun' => !empty($task['supportsDryRun']),
            'unavailable' => (string)($task['unavailableReason'] ?? ''),
            'core' => !empty($task['core'])
        );
    }
    ksort($areas, SORT_STRING);

    $page = DevTools::createPage('console.php', 'plugin.devtools.console.tpl');
    $page->assignSmartyVariable('areas', $areas);
    $page->assignSmartyVariable('result', $result);
    $page->assignSmartyVariable('commandLine', $commandLine);
    $page->assignSmartyVariable('errors', $errors);
    $page->assignSmartyVariable('consoleDescription', $gL10n->get('PLG_DEVTOOLS_CONSOLE_DESC') . ' ' . $gL10n->get('PLG_DEVTOOLS_TASKS_ACTING', array($context['as'], $context['organization'])));
    $page->assignSmartyVariable('runUrl', DevTools::url('console.php', array('mode' => 'run')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

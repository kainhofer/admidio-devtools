<?php
/**
 ***********************************************************************************************
 * DevTools - create the skeleton of a new plugin.
 *
 * Parameters:
 * mode : form (default), create (POST; target = directory or zip)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Scaffold\PluginScaffolder;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('form', 'create'), 'defaultValue' => 'form'));

    if ($getMode === 'create') {
        DevTools::requirePost();
        try {
            $scaffolder = new PluginScaffolder(array(
                'id' => trim((string)($_POST['id'] ?? '')),
                'name' => (string)($_POST['name'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'author' => (string)($_POST['author'] ?? ''),
                'page' => !empty($_POST['page']),
                'cli' => !empty($_POST['cli']),
                'setting' => !empty($_POST['setting']),
                'tests' => !empty($_POST['tests']),
                'releaseWorkflow' => !empty($_POST['release_workflow'])
            ));

            if (($_POST['target'] ?? '') === 'zip') {
                $zip = $scaffolder->createZip();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $scaffolder->getId() . '.zip"');
                header('Content-Length: ' . filesize($zip));
                readfile($zip);
                @unlink($zip);
                exit();
            }

            $directory = ADMIDIO_PATH . FOLDER_PLUGINS . '/' . $scaffolder->getId();
            $scaffolder->writeTo($directory);
            DevTools::flash('success', $gL10n->get('PLG_DEVTOOLS_SCAFFOLD_CREATED', array($directory)));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            DevTools::flash('danger', $exception->getMessage());
        }
        admRedirect(DevTools::url('scaffold.php'));
    }

    $page = DevTools::createPage('scaffold.php', 'plugin.devtools.scaffold.tpl');
    $page->assignSmartyVariable('createUrl', DevTools::url('scaffold.php', array('mode' => 'create')));
    $page->assignSmartyVariable('author', trim($gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME')));
    $page->assignSmartyVariable('pluginManagerUrl', ADMIDIO_URL . FOLDER_MODULES . '/plugins.php');
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

<?php
/**
 ***********************************************************************************************
 * DevTools - log files and caught exceptions.
 *
 * Parameters:
 * view      : files (default), exceptions
 * file      : the log file to show
 * level     : minimum level
 * search    : text the entries must contain
 * limit     : number of entries
 * exception : show the caught exception with this id
 * mode      : download (of file), delete_exceptions (POST)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Logs\ExceptionCatcher;
use AdmidioPlugin\DevTools\Logs\LogReader;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getView = admFuncVariableIsValid($_GET, 'view', 'string', array('validValues' => array('files', 'exceptions'), 'defaultValue' => 'files'));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('show', 'download', 'delete_exceptions'), 'defaultValue' => 'show'));
    $getFile = admFuncVariableIsValid($_GET, 'file', 'string');
    $getLevel = admFuncVariableIsValid($_GET, 'level', 'string', array('validValues' => array_merge(array(''), LogReader::LEVELS), 'defaultValue' => ''));
    $getSearch = admFuncVariableIsValid($_GET, 'search', 'string');
    $getLimit = admFuncVariableIsValid($_GET, 'limit', 'int', array('defaultValue' => 200));
    $getException = admFuncVariableIsValid($_GET, 'exception', 'string');

    $reader = new LogReader(ADMIDIO_PATH . FOLDER_DATA . '/logs');

    if ($getMode === 'delete_exceptions') {
        DevTools::requirePost();
        ExceptionCatcher::deleteAll();
        admRedirect(DevTools::url('logs.php', array('view' => 'exceptions')));
    }

    if ($getMode === 'download') {
        $path = $reader->getPath($getFile);
        if ($path === null) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit();
    }

    $files = array();
    foreach ($reader->getFiles() as $id => $file) {
        $files[] = array('id' => $id, 'name' => $file['name'], 'size' => FileSystemUtils::getHumanReadableBytes($file['size']), 'time' => date('Y-m-d H:i', $file['mtime']));
    }
    if ($getFile === '' && $files !== array()) {
        $getFile = $files[0]['id'];
    }

    $entries = array();
    $path = $getView === 'files' ? $reader->getPath($getFile) : null;
    if ($path !== null) {
        $entries = $reader->read($path, max(1, min(5000, $getLimit)), $getLevel, $getSearch);
    }

    $exceptions = array();
    $exception = null;
    if ($getView === 'exceptions') {
        if ($getException !== '') {
            $exception = ExceptionCatcher::read($getException);
        }
        if ($exception === null) {
            foreach (ExceptionCatcher::list() as $entry) {
                $entry['link'] = DevTools::url('logs.php', array('view' => 'exceptions', 'exception' => $entry['id']));
                $entry['first'] = $entry['exceptions'][0] ?? array();
                if (isset($entry['first']['file'])) {
                    $entry['first']['file'] = str_replace(ADMIDIO_PATH . '/', '', $entry['first']['file']);
                }
                $exceptions[] = $entry;
            }
        }
    }

    $views = array();
    foreach (array('files' => 'PLG_DEVTOOLS_LOG_FILES', 'exceptions' => 'PLG_DEVTOOLS_EXCEPTIONS') as $view => $label) {
        $views[] = array('url' => DevTools::url('logs.php', array('view' => $view)), 'label' => $gL10n->get($label), 'active' => $view === $getView);
    }

    $page = DevTools::createPage('logs.php', 'plugin.devtools.logs.tpl');
    $page->assignSmartyVariable('view', $getView);
    $page->assignSmartyVariable('views', $views);
    $page->assignSmartyVariable('files', $files);
    $page->assignSmartyVariable('file', $getFile);
    $page->assignSmartyVariable('levels', LogReader::LEVELS);
    $page->assignSmartyVariable('level', $getLevel);
    $page->assignSmartyVariable('search', $getSearch);
    $page->assignSmartyVariable('limit', $getLimit);
    $page->assignSmartyVariable('entries', $entries);
    $page->assignSmartyVariable('formUrl', DevTools::getPlugin()->getUrl('logs.php'));
    $page->assignSmartyVariable('downloadUrl', DevTools::url('logs.php', array('mode' => 'download', 'file' => $getFile)));
    $page->assignSmartyVariable('exceptions', $exceptions);
    $page->assignSmartyVariable('exception', $exception);
    $page->assignSmartyVariable('exceptionsUrl', DevTools::url('logs.php', array('view' => 'exceptions')));
    $page->assignSmartyVariable('deleteExceptionsUrl', DevTools::url('logs.php', array('mode' => 'delete_exceptions')));
    $page->assignSmartyVariable('catcherEnabled', $gSettingsManager->getBool('devtools_exception_catcher'));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

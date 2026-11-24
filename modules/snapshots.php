<?php
/**
 ***********************************************************************************************
 * DevTools - snapshots of files and database.
 *
 * Creating and deleting happens here; restoring happens on the standalone page restore.php, which
 * works without the Admidio bootstrap.
 *
 * Parameters:
 * mode : create, delete, renew_key (POST)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\FileSystemUtils;
use AdmidioPlugin\DevTools\DevTools;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $store = DevTools::getSnapshotStore();
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('list', 'create', 'delete', 'renew_key'), 'defaultValue' => 'list'));

    if ($getMode !== 'list') {
        DevTools::requirePost();
        try {
            switch ($getMode) {
                case 'create':
                    $manifest = $store->create(
                        trim((string)($_POST['label'] ?? '')),
                        trim((string)($_POST['comment'] ?? '')),
                        !empty($_POST['include_data']),
                        ADMIDIO_VERSION_TEXT
                    );
                    DevTools::flash('success', $gL10n->get('PLG_DEVTOOLS_SNAPSHOT_CREATED', array($manifest['name'], $manifest['fileCount'], count($manifest['tables']))));
                    break;
                case 'delete':
                    $store->delete((string)($_POST['snapshot'] ?? ''));
                    DevTools::flash('success', $gL10n->get('PLG_DEVTOOLS_SNAPSHOT_DELETED', array((string)$_POST['snapshot'])));
                    break;
                case 'renew_key':
                    $store->renewRestoreKey();
                    DevTools::flash('success', $gL10n->get('PLG_DEVTOOLS_RESTORE_KEY_RENEWED'));
                    break;
            }
        } catch (RuntimeException $exception) {
            DevTools::flash('danger', $exception->getMessage());
        }
        admRedirect(DevTools::url('snapshots.php'));
    }

    $snapshots = array();
    foreach ($store->list() as $snapshot) {
        $snapshot['fileSizeText'] = FileSystemUtils::getHumanReadableBytes((int)$snapshot['fileSize']);
        $snapshot['databaseSizeText'] = FileSystemUtils::getHumanReadableBytes((int)$snapshot['databaseSize']);
        $snapshot['tableCount'] = count($snapshot['tables']);
        $snapshot['rowCount'] = array_sum($snapshot['tables']);
        $snapshots[] = $snapshot;
    }

    $config = $store->getConfig();
    $page = DevTools::createPage('snapshots.php', 'plugin.devtools.snapshots.tpl');
    $page->assignSmartyVariable('snapshots', $snapshots);
    $page->assignSmartyVariable('snapshotPath', $config->snapshotPath);
    $page->assignSmartyVariable('snapshotPathInWebRoot', $config->relativeToRoot($config->snapshotPath) !== null);
    $page->assignSmartyVariable('restoreKey', $store->getRestoreKey(false));
    $page->assignSmartyVariable('restoreUrl', DevTools::getPlugin()->getUrl('restore.php'));
    $page->assignSmartyVariable('restoreCommand', 'php ' . FOLDER_PLUGINS . '/' . DevTools::ID . '/modules/restore.php --snapshot=NAME');
    $page->assignSmartyVariable('createUrl', DevTools::url('snapshots.php', array('mode' => 'create')));
    $page->assignSmartyVariable('deleteUrl', DevTools::url('snapshots.php', array('mode' => 'delete')));
    $page->assignSmartyVariable('renewKeyUrl', DevTools::url('snapshots.php', array('mode' => 'renew_key')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

<?php
/**
 ***********************************************************************************************
 * DevTools - snapshots of files and database.
 *
 * Creating and deleting happens here; restoring happens on the standalone page restore.php, which
 * works without the Admidio bootstrap.
 *
 * Parameters:
 * mode : create, delete, update (label, comment), renew_key, directory (path, move) - all POST
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\FileSystemUtils;
use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Snapshot\SnapshotConfig;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $store = DevTools::getSnapshotStore();
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('list', 'create', 'delete', 'update', 'renew_key', 'directory'), 'defaultValue' => 'list'));

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
                case 'update':
                    $store->update((string)($_POST['snapshot'] ?? ''), trim((string)($_POST['label'] ?? '')), trim((string)($_POST['comment'] ?? '')));
                    DevTools::flash('success', $gL10n->get('PLG_DEVTOOLS_SNAPSHOT_UPDATED', array((string)$_POST['snapshot'])));
                    break;
                case 'directory':
                    $config = $store->getConfig();
                    if ($config->snapshotPathSource === SnapshotConfig::SOURCE_CONFIG) {
                        throw new RuntimeException($gL10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_FROM_CONFIG'));
                    }
                    $path = trim((string)($_POST['path'] ?? ''));
                    $target = SnapshotConfig::resolveSnapshotPath($config->rootPath, $path);
                    if ($target !== $config->snapshotPath) {
                        if (!empty($_POST['move'])) {
                            $store->moveTo($target);
                        }
                        SnapshotConfig::writePointer($config->rootPath, $path);
                        $newStore = DevTools::getSnapshotStore();
                        $newStore->ensureDirectory();
                        if (!is_writable($target)) {
                            throw new RuntimeException($gL10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_NOT_WRITABLE', array($target)));
                        }
                    }
                    DevTools::flash('success', $gL10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_CHANGED', array($target)));
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
    $restorePage = DevTools::getPlugin()->getUrl('restore.php');
    foreach ($snapshots as &$snapshot) {
        $snapshot['restoreUrl'] = $restorePage . '?snapshot=' . rawurlencode($snapshot['name']);
    }
    unset($snapshot);

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
    $page->assignSmartyVariable('updateUrl', DevTools::url('snapshots.php', array('mode' => 'update')));
    $page->assignSmartyVariable('directoryUrl', DevTools::url('snapshots.php', array('mode' => 'directory')));
    $page->assignSmartyVariable('snapshotPathSource', $config->snapshotPathSource);
    $page->assignSmartyVariable('snapshotPathSetting', SnapshotConfig::readPointer($config->rootPath));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

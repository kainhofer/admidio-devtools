<?php
/**
 ***********************************************************************************************
 * DevTools - the restore page.
 *
 * This page restores a snapshot of the files and the database. It is deliberately independent of
 * Admidio: it does not include system/common.php, uses no Admidio class and no autoloader, only
 * adm_my_files/config.php, plain PDO and the classes in ../src/Snapshot, which no snapshot contains
 * and no restore overwrites. So it works even when a pull request - or a previous restore - broke
 * the installation, and restoring another snapshot is always possible.
 *
 * Access: $gDebug = true in config.php, and the restore key that the DevTools snapshot page shows.
 *
 * Command line: php plugins/devtools/modules/restore.php [--host=HOST] --list
 *               php plugins/devtools/modules/restore.php [--host=HOST] --snapshot=NAME
 * --host is needed for a config.php that chooses its values by $_SERVER['HTTP_HOST'].
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\Snapshot\SnapshotConfig;
use AdmidioPlugin\DevTools\Snapshot\SnapshotRestorer;
use AdmidioPlugin\DevTools\Snapshot\SnapshotStore;

foreach (array('SnapshotConfig', 'FileScanner', 'DatabaseDumper', 'SnapshotStore', 'SnapshotRestorer') as $devtoolsClass) {
    require_once __DIR__ . '/../src/Snapshot/' . $devtoolsClass . '.php';
}

/**
 * The texts of the page, read directly from the language files of the plugin.
 */
function devtoolsRestoreText(string $key, array $params = array()): string
{
    static $texts = null;

    if ($texts === null) {
        $language = 'en';
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if (str_starts_with($accept, 'de')) {
            $language = str_starts_with($accept, 'de-de') ? 'de-DE' : 'de';
        }
        $texts = array();
        foreach (array_unique(array('en', $language)) as $file) {
            $xml = @simplexml_load_file(__DIR__ . '/../languages/' . $file . '.xml');
            if ($xml !== false) {
                foreach ($xml->string as $string) {
                    $texts[(string)$string['name']] = str_replace('\\n', "\n", (string)$string);
                }
            }
        }
    }

    $text = $texts[$key] ?? $key;
    foreach (array_values($params) as $index => $value) {
        $text = str_replace('#VAR' . ($index + 1) . '#', (string)$value, $text);
    }
    return $text;
}

function devtoolsRestoreEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$devtoolsRoot = dirname(__DIR__, 3);

// ------------------------------------------------------------------------------------------------
// command line
// ------------------------------------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    $options = getopt('', array('list', 'snapshot:', 'host:', 'help'));
    if (isset($options['host'])) {
        // for a config.php that chooses its values by host, like the --host of the Admidio CLI
        $_SERVER['HTTP_HOST'] = (string)$options['host'];
    }
    try {
        $config = SnapshotConfig::load($devtoolsRoot);
        $store = new SnapshotStore($config);

        if (isset($options['snapshot'])) {
            $restorer = new SnapshotRestorer($store, static function (string $key, array $params): void {
                echo devtoolsRestoreText($key, $params), PHP_EOL;
            });
            $restorer->restore((string)$options['snapshot']);
            echo devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_DONE'), PHP_EOL;
            exit(0);
        }

        if (isset($options['list'])) {
            foreach ($store->list() as $snapshot) {
                echo $snapshot['name'], "\t", $snapshot['version'], "\t", $snapshot['comment'], PHP_EOL;
            }
            exit(0);
        }

        echo 'Usage: php ', $argv[0], ' [--host=HOST] --list | --snapshot=NAME', PHP_EOL;
        exit(isset($options['help']) ? 0 : 2);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

// ------------------------------------------------------------------------------------------------
// browser
// ------------------------------------------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$messages = array();
$progress = array();
$snapshots = array();
$authorized = false;
$restored = false;
$formToken = '';

try {
    $config = SnapshotConfig::load($devtoolsRoot);

    if (!$config->debug) {
        http_response_code(403);
        $messages[] = array('danger', devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_NO_DEBUG'));
    } else {
        $store = new SnapshotStore($config);
        $key = $store->getRestoreKey(false);
        $cookieName = 'ADMIDIO_DEVTOOLS_RESTORE';
        $cookieValue = $key === null ? '' : hash_hmac('sha256', 'cookie', $key);

        if ($key === null) {
            $messages[] = array('warning', devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_NO_SNAPSHOTS'));
        } elseif (isset($_POST['restore_key'])) {
            if (hash_equals($key, trim((string)$_POST['restore_key']))) {
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
                setcookie($cookieName, $cookieValue, array(
                    'expires' => time() + 30 * 86400,
                    'path' => dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')) . '/',
                    'secure' => $https,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ));
                $authorized = true;
            } else {
                usleep(random_int(500000, 1000000));
                $messages[] = array('danger', devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_WRONG_KEY'));
            }
        } elseif (hash_equals($cookieValue, (string)($_COOKIE[$cookieName] ?? ''))) {
            $authorized = true;
        }

        if ($authorized) {
            $formToken = hash_hmac('sha256', 'restore-form', (string)$key);

            if (($_POST['mode'] ?? '') === 'restore') {
                if (!hash_equals($formToken, (string)($_POST['form_token'] ?? ''))) {
                    throw new RuntimeException(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_INVALID_FORM'));
                }
                if (empty($_POST['confirm'])) {
                    throw new RuntimeException(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_CONFIRM_MISSING'));
                }
                $restorer = new SnapshotRestorer($store, static function (string $textKey, array $params) use (&$progress): void {
                    $progress[] = devtoolsRestoreText($textKey, $params);
                });
                $restorer->restore((string)($_POST['snapshot'] ?? ''));
                $restored = true;
                $messages[] = array('success', devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_DONE'));
            }

            $snapshots = $store->list();
        }
    }
} catch (Throwable $exception) {
    $messages[] = array('danger', $exception->getMessage());
    if ($authorized && isset($store)) {
        try {
            $snapshots = $store->list();
        } catch (Throwable) {
            // the list stays empty
        }
    }
}

$loginUrl = '../../../system/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_TITLE')); ?></title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f4f5f7; color: #212529; }
        main { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.5rem; }
        .box { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .alert { padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1rem; }
        .alert-danger { background: #f8d7da; color: #58151c; }
        .alert-warning { background: #fff3cd; color: #664d03; }
        .alert-success { background: #d1e7dd; color: #0a3622; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .4rem .5rem; border-bottom: 1px solid #dee2e6; vertical-align: top; }
        button { background: #b02a37; color: #fff; border: 0; border-radius: .375rem; padding: .5rem 1rem; font-size: 1rem; cursor: pointer; }
        button.secondary { background: #0d6efd; }
        input[type=password] { padding: .4rem; width: 24rem; max-width: 100%; }
        .muted { color: #6c757d; font-size: .875rem; }
        ol { margin: 0; }
    </style>
</head>
<body>
<main>
    <h1><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_TITLE')); ?></h1>
    <p class="muted"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_INTRO')); ?></p>

    <?php foreach ($messages as $message) { ?>
        <div class="alert alert-<?php echo $message[0]; ?>"><?php echo nl2br(devtoolsRestoreEscape($message[1])); ?></div>
    <?php } ?>

    <?php if ($progress !== array()) { ?>
        <div class="box"><ol><?php foreach ($progress as $line) { ?><li><?php echo devtoolsRestoreEscape($line); ?></li><?php } ?></ol></div>
    <?php } ?>

    <?php if ($restored) { ?>
        <p><a href="<?php echo devtoolsRestoreEscape($loginUrl); ?>"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_TO_LOGIN')); ?></a></p>
    <?php } ?>

    <?php if (!$authorized && isset($key) && $key !== null) { ?>
        <form class="box" method="post" autocomplete="off">
            <p><label for="restore_key"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_KEY')); ?></label></p>
            <p><input type="password" id="restore_key" name="restore_key" required autofocus></p>
            <p class="muted"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_KEY_DESC', array(SnapshotStore::KEY_FILE))); ?></p>
            <button type="submit" class="secondary"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_UNLOCK')); ?></button>
        </form>
    <?php } ?>

    <?php if ($authorized) { ?>
        <?php if ($snapshots === array()) { ?>
            <div class="alert alert-warning"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_NO_SNAPSHOTS')); ?></div>
        <?php } else { ?>
            <form class="box" method="post" onsubmit="this.querySelector('button').disabled = true;">
                <input type="hidden" name="mode" value="restore">
                <input type="hidden" name="form_token" value="<?php echo devtoolsRestoreEscape($formToken); ?>">
                <table>
                    <thead><tr>
                        <th></th>
                        <th><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_SNAPSHOT')); ?></th>
                        <th><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_VERSION')); ?></th>
                        <th><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_SNAPSHOT_COMMENT')); ?></th>
                        <th><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_SNAPSHOT_SCOPE')); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($snapshots as $index => $snapshot) { ?>
                        <tr>
                            <td><input type="radio" name="snapshot" id="snapshot_<?php echo $index; ?>" value="<?php echo devtoolsRestoreEscape($snapshot['name']); ?>" required></td>
                            <td><label for="snapshot_<?php echo $index; ?>"><?php echo devtoolsRestoreEscape($snapshot['name']); ?></label></td>
                            <td><?php echo devtoolsRestoreEscape($snapshot['version']); ?></td>
                            <td><?php echo devtoolsRestoreEscape($snapshot['comment']); ?></td>
                            <td><?php echo devtoolsRestoreEscape(devtoolsRestoreText($snapshot['includeData'] ? 'PLG_DEVTOOLS_SNAPSHOT_SCOPE_DATA' : 'PLG_DEVTOOLS_SNAPSHOT_SCOPE_CODE')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                <p><label><input type="checkbox" name="confirm" value="1" required> <?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_CONFIRM')); ?></label></p>
                <button type="submit"><?php echo devtoolsRestoreEscape(devtoolsRestoreText('PLG_DEVTOOLS_RESTORE_BUTTON')); ?></button>
            </form>
        <?php } ?>
    <?php } ?>
</main>
</body>
</html>

<?php
/**
 ***********************************************************************************************
 * DevTools - command-line commands.
 *
 * The commands only exist on a development installation ($gDebug = true in config.php).
 * Restoring a snapshot is not an Admidio command, because it has to work while Admidio is broken:
 *     php plugins/devtools/modules/restore.php --list | --snapshot=NAME
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Cli\CliTaskRegistry;
use AdmidioPlugin\DevTools\Cli\DevToolsCli;
use AdmidioPlugin\DevTools\DevTools;

if (PHP_SAPI !== 'cli') {
    exit('This page may not be called directly!');
}

if (!DevTools::isDevelopmentInstallation()) {
    return;
}

$opt = static function (string $name, string $description, string $value = 'VALUE', bool $flag = false, array $values = array()): array {
    $definition = array('name' => $name, 'description' => $description, 'required' => false, 'multiple' => false, 'flag' => $flag);
    if (!$flag) {
        $definition['value'] = $value;
    }
    if ($values !== array()) {
        $definition['values'] = $values;
    }
    return $definition;
};
$format = $opt('format', 'Output format.', 'FORMAT', false, array('table', 'record', 'json', 'csv', 'md', 'dokuwiki'));

CliTaskRegistry::register(
    'devtools:snapshot-create',
    DevTools::ID,
    array(DevToolsCli::class, 'snapshotCreate'),
    'Take a snapshot of the files and the database of this installation.',
    'devtools:snapshot-create [--label=LABEL] [--comment=TEXT] [--include-data] [--format=FORMAT]',
    array(),
    array(
        $opt('label', 'Short name, becomes part of the snapshot name.', 'LABEL'),
        $opt('comment', 'Free text shown in the list.', 'TEXT'),
        $opt('include-data', 'Include adm_my_files (uploads, photos, documents) in the snapshot.', '', true),
        $format
    )
);

CliTaskRegistry::register(
    'devtools:snapshot-list',
    DevTools::ID,
    array(DevToolsCli::class, 'snapshotList'),
    'List the snapshots.',
    'devtools:snapshot-list [--format=FORMAT]',
    array(),
    array($format),
    array(),
    CliTaskRegistry::ACCESS_ADMINISTRABLE
);

CliTaskRegistry::register(
    'devtools:snapshot-delete',
    DevTools::ID,
    array(DevToolsCli::class, 'snapshotDelete'),
    'Delete a snapshot and the stored files no other snapshot needs.',
    'devtools:snapshot-delete NAME --yes',
    array(array('name' => 'name', 'description' => 'Name of the snapshot.', 'required' => true)),
    array($opt('yes', 'Confirm the deletion.', '', true))
);

CliTaskRegistry::register(
    'devtools:make-plugin',
    DevTools::ID,
    array(DevToolsCli::class, 'makePlugin'),
    'Create the skeleton of a new plugin in plugins/<id>/.',
    'devtools:make-plugin ID [--name=NAME] [--description=TEXT] [--author=AUTHOR] [--page] [--cli] [--setting] [--tests] [--release-workflow]',
    array(array('name' => 'id', 'description' => 'Plugin ID: lowercase letters, digits and hyphens.', 'required' => true)),
    array(
        $opt('name', 'Name of the plugin.', 'NAME'),
        $opt('description', 'One sentence about the plugin.', 'TEXT'),
        $opt('author', 'Author of the plugin.', 'AUTHOR'),
        $opt('page', 'Add a page with a template.', '', true),
        $opt('cli', 'Add a CLI command.', '', true),
        $opt('setting', 'Add an example setting.', '', true),
        $opt('tests', 'Add a PHPUnit test of the manifest.', '', true),
        $opt('release-workflow', 'Add the GitHub release workflow.', '', true)
    )
);

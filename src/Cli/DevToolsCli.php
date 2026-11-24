<?php
namespace AdmidioPlugin\DevTools\Cli;

use Admidio\Infrastructure\Cli\CliApplication;
use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Scaffold\PluginScaffolder;

/**
 ***********************************************************************************************
 * The devtools:* commands.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class DevToolsCli
{
    public static function snapshotCreate(array $arguments, array $options): int
    {
        $manifest = DevTools::getSnapshotStore()->create(
            CliApplication::optionString($options, 'label'),
            CliApplication::optionString($options, 'comment'),
            CliApplication::optionExists($options, 'include-data'),
            ADMIDIO_VERSION_TEXT
        );

        CliApplication::writeRows(array(self::row($manifest)), CliApplication::optionString($options, 'format', 'record'), $options);
        return 0;
    }

    public static function snapshotList(array $arguments, array $options): int
    {
        $rows = array_map(array(self::class, 'row'), DevTools::getSnapshotStore()->list());
        CliApplication::writeRows($rows, CliApplication::optionString($options, 'format', 'table'), $options);
        return 0;
    }

    public static function snapshotDelete(array $arguments, array $options): int
    {
        $name = CliApplication::requireArgument($arguments, 0, 'name');
        $store = DevTools::getSnapshotStore();
        $store->read($name);
        CliApplication::confirm('Delete the snapshot ' . $name . '?', $options);
        $store->delete($name);
        CliApplication::writeSuccess('Snapshot ' . $name . ' deleted.', $options);
        return 0;
    }

    public static function makePlugin(array $arguments, array $options): int
    {
        $scaffolder = new PluginScaffolder(array(
            'id' => CliApplication::requireArgument($arguments, 0, 'id'),
            'name' => CliApplication::optionString($options, 'name'),
            'description' => CliApplication::optionString($options, 'description'),
            'author' => CliApplication::optionString($options, 'author'),
            'page' => CliApplication::optionExists($options, 'page'),
            'cli' => CliApplication::optionExists($options, 'cli'),
            'setting' => CliApplication::optionExists($options, 'setting'),
            'tests' => CliApplication::optionExists($options, 'tests'),
            'releaseWorkflow' => CliApplication::optionExists($options, 'release-workflow')
        ));
        $directory = ADMIDIO_PATH . FOLDER_PLUGINS . '/' . $scaffolder->getId();
        $scaffolder->writeTo($directory);
        CliApplication::writeSuccess('Plugin created in ' . $directory . '. Install it in the plugin manager.', $options);
        return 0;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>
     */
    private static function row(array $manifest): array
    {
        return array(
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'created' => $manifest['created'],
            'data' => $manifest['includeData'] ? 'yes' : 'no',
            'files' => $manifest['fileCount'],
            'tables' => count($manifest['tables']),
            'comment' => $manifest['comment']
        );
    }
}

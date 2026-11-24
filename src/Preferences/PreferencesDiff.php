<?php
namespace AdmidioPlugin\DevTools\Preferences;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Throwable;

/**
 ***********************************************************************************************
 * Compares the preferences stored for an organization with their defaults.
 *
 * - **changed**: the stored value differs from the default,
 * - **missing**: a preference is defined, but the organization has no row for it,
 * - **unknown**: a row without a definition - left behind by a removed plugin, an old version or a
 *   plugin that is not loaded in this request,
 * - **default**: the stored value is the default.
 *
 * The definitions are those of PreferenceDefinitions: the core ones and those of the plugins loaded
 * in this request. Values of sensitive preferences are not shown.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class PreferencesDiff
{
    public const STATUS_CHANGED = 'changed';
    public const STATUS_MISSING = 'missing';
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_DEFAULT = 'default';

    public const MASK = '••••••';

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<int,array{name:string, owner:string, status:string, default:?string, value:?string, sensitive:bool}>
     */
    public function compare(int $organizationId): array
    {
        $definitions = PreferenceDefinitions::all();
        $defaults = array();
        try {
            // a default provider may need something this request does not have

            $defaults = PreferenceDefinitions::defaults();
        } catch (Throwable) {
            foreach ($definitions as $name => $definition) {
                $defaults[$name] = isset($definition['default']) ? (string)$definition['default'] : null;
            }
        }

        $stored = array();
        $statement = $this->database->queryPrepared(
            'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ?',
            array($organizationId)
        );
        while ($row = $statement->fetch()) {
            $stored[(string)$row['prf_name']] = (string)$row['prf_value'];
        }

        $names = array_unique(array_merge(array_keys($definitions), array_keys($stored)));
        sort($names, SORT_STRING);

        $rows = array();
        foreach ($names as $name) {
            $defined = array_key_exists($name, $definitions);
            $sensitive = $defined && !empty($definitions[$name]['sensitive']) || self::looksSensitive($name);
            $default = $defaults[$name] ?? null;
            $value = $stored[$name] ?? null;

            if (!$defined) {
                $status = self::STATUS_UNKNOWN;
            } elseif ($value === null) {
                $status = self::STATUS_MISSING;
            } elseif ($default !== null && $value !== $default) {
                $status = self::STATUS_CHANGED;
            } else {
                $status = self::STATUS_DEFAULT;
            }

            $rows[] = array(
                'name' => $name,
                'owner' => self::owner($name, $defined),
                'status' => $status,
                'default' => $sensitive && $default !== null && $default !== '' ? self::MASK : $default,
                'value' => $sensitive && $value !== null && $value !== '' ? self::MASK : $value,
                'sensitive' => $sensitive
            );
        }
        return $rows;
    }

    private static function owner(string $name, bool $defined): string
    {
        if (in_array($name, PreferenceDefinitions::coreNames(), true)) {
            return 'core';
        }
        try {
            $plugin = PluginRegistry::getOwnerOfSetting($name);
            if ($plugin !== null) {
                return $plugin->id;
            }
        } catch (Throwable) {
            // unknown owner
        }
        if (preg_match('/^plugin_([a-z0-9_-]+)_(enabled|access)$/', $name, $match)) {
            return $match[1];
        }
        return $defined ? 'core' : '?';
    }

    /**
     * Names that hold secrets even if no definition says so (e.g. of a plugin that is not loaded).
     */
    private static function looksSensitive(string $name): bool
    {
        return preg_match('/(password|secret|token|private_key|_key$|apikey|api_key)/', $name) === 1;
    }
}

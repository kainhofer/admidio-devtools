<?php
/**
 ***********************************************************************************************
 * DevTools - the preferences of an organization compared with their defaults.
 *
 * Parameters:
 * org : ID of the organization (default: the current one)
 * all : 1 = also the preferences that have their default value
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Organizations\OrganizationSwitcher;
use AdmidioPlugin\DevTools\Preferences\PreferencesDiff;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getOrganization = admFuncVariableIsValid($_GET, 'org', 'int', array('defaultValue' => (int)$gCurrentOrgId));
    $getAll = admFuncVariableIsValid($_GET, 'all', 'bool', array('defaultValue' => false));

    $organizations = (new OrganizationSwitcher($gDb))->getOrganizations((int)$gCurrentUserId, (int)$gCurrentOrgId);
    $organizationIds = array_column($organizations, 'id');
    if (!in_array($getOrganization, $organizationIds, true)) {
        $getOrganization = (int)$gCurrentOrgId;
    }

    $rows = (new PreferencesDiff($gDb))->compare($getOrganization);
    $counts = array_count_values(array_column($rows, 'status'));
    if (!$getAll) {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => $row['status'] !== PreferencesDiff::STATUS_DEFAULT));
    }

    $page = DevTools::createPage('preferences.php', 'plugin.devtools.preferences.tpl');
    $page->assignSmartyVariable('rows', $rows);
    $page->assignSmartyVariable('counts', $counts);
    $page->assignSmartyVariable('organizations', $organizations);
    $page->assignSmartyVariable('organization', $getOrganization);
    $page->assignSmartyVariable('showAll', $getAll);
    $page->assignSmartyVariable('formUrl', DevTools::getPlugin()->getUrl('preferences.php'));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

<?php
/**
 ***********************************************************************************************
 * DevTools - the preferences of an organization compared with their defaults.
 *
 * Parameters:
 * org : ID of the organization (default: the current one)
 *
 * All rows are sent; filtering by status, owner and text and sorting happen in the browser.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Preferences\PreferencesDiff;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getOrganization = admFuncVariableIsValid($_GET, 'org', 'int', array('defaultValue' => (int)$gCurrentOrgId));

    $organizations = array();
    $statement = $gDb->queryPrepared('SELECT org_id, org_shortname, org_longname FROM ' . TBL_ORGANIZATIONS . ' ORDER BY org_longname');
    while ($row = $statement->fetch()) {
        $organizations[] = array('id' => (int)$row['org_id'], 'shortname' => $row['org_shortname'], 'longname' => $row['org_longname']);
    }
    $organizationIds = array_column($organizations, 'id');
    if (!in_array($getOrganization, $organizationIds, true)) {
        $getOrganization = (int)$gCurrentOrgId;
    }

    $rows = (new PreferencesDiff($gDb))->compare($getOrganization);
    $counts = array_count_values(array_column($rows, 'status'));
    $owners = array_values(array_unique(array_column($rows, 'owner')));
    sort($owners, SORT_STRING);

    $page = DevTools::createPage('preferences.php', 'plugin.devtools.preferences.tpl');
    $page->assignSmartyVariable('rows', $rows);
    $page->assignSmartyVariable('counts', $counts);
    $page->assignSmartyVariable('organizations', $organizations);
    $page->assignSmartyVariable('organization', $getOrganization);
    $page->assignSmartyVariable('owners', $owners);
    $page->assignSmartyVariable('statuses', array(
        PreferencesDiff::STATUS_CHANGED => array('label' => $gL10n->get('PLG_DEVTOOLS_PREFERENCE_CHANGED'), 'class' => 'primary', 'selected' => true),
        PreferencesDiff::STATUS_MISSING => array('label' => $gL10n->get('PLG_DEVTOOLS_PREFERENCE_MISSING'), 'class' => 'warning', 'selected' => true),
        PreferencesDiff::STATUS_UNKNOWN => array('label' => $gL10n->get('PLG_DEVTOOLS_PREFERENCE_UNKNOWN'), 'class' => 'danger', 'selected' => true),
        PreferencesDiff::STATUS_DEFAULT => array('label' => $gL10n->get('PLG_DEVTOOLS_PREFERENCE_DEFAULT'), 'class' => 'secondary', 'selected' => false)
    ));
    $page->assignSmartyVariable('formUrl', DevTools::getPlugin()->getUrl('preferences.php'));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

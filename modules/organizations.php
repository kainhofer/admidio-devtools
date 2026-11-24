<?php
/**
 ***********************************************************************************************
 * DevTools - switch to another organization without logging out.
 *
 * Parameters:
 * mode : switch (POST, with organization = short name)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Organizations\OrganizationSwitcher;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('list', 'switch'), 'defaultValue' => 'list'));
    $switcher = new OrganizationSwitcher($gDb);

    if ($getMode === 'switch') {
        DevTools::requirePost();
        $switcher->switchTo((string)($_POST['organization'] ?? ''));
        admRedirect(ADMIDIO_URL . FOLDER_MODULES . '/overview.php');
    }

    $page = DevTools::createPage('organizations.php', 'plugin.devtools.organizations.tpl');
    $page->assignSmartyVariable('organizations', $switcher->getOrganizations((int)$gCurrentUserId, (int)$gCurrentOrgId));
    $page->assignSmartyVariable('switchUrl', DevTools::url('organizations.php', array('mode' => 'switch')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

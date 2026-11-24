<?php
namespace AdmidioPlugin\DevTools\Organizations;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Session\Entity\AutoLogin;
use Admidio\Session\Entity\Session;
use ReflectionProperty;

/**
 ***********************************************************************************************
 * Switch the current session to another organization without logging out.
 *
 * Only organizations in which the user has an active membership are offered - the same ones the
 * login dialog would let them log in to. The switch sets the organization of the session and
 * discards the objects the session keeps (organization, preferences, profile fields, user, menu,
 * language), so the next request builds all of them anew for the new organization. With an
 * auto-login ("remember me") the auto-login record is moved to the new organization as well.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class OrganizationSwitcher
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * All organizations, with the information whether the user is a member.
     * @return array<int,array{id:int, shortname:string, longname:string, member:bool, current:bool}>
     */
    public function getOrganizations(int $userId, int $currentOrgId): array
    {
        $sql = 'SELECT org_id, org_shortname, org_longname,
                       (SELECT COUNT(*)
                          FROM ' . TBL_MEMBERS . '
                    INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                    INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                         WHERE mem_usr_id = ?
                           AND mem_begin <= ?
                           AND mem_end > ?
                           AND rol_valid = true
                           AND (cat_org_id = org_id OR cat_org_id IS NULL)) AS memberships
                  FROM ' . TBL_ORGANIZATIONS . '
              ORDER BY org_longname, org_shortname';
        $today = DATE_NOW;
        $statement = $this->database->queryPrepared($sql, array($userId, $today, $today));

        $organizations = array();
        while ($row = $statement->fetch()) {
            $organizations[] = array(
                'id' => (int)$row['org_id'],
                'shortname' => (string)$row['org_shortname'],
                'longname' => (string)$row['org_longname'],
                'member' => (int)$row['memberships'] > 0,
                'current' => (int)$row['org_id'] === $currentOrgId
            );
        }
        return $organizations;
    }

    /**
     * Switch the session of the current user to the organization.
     * @throws Exception if the user is no member there
     */
    public function switchTo(string $shortname): void
    {
        global $gCurrentSession, $gCurrentUserId, $gCurrentOrgId;

        $target = null;
        foreach ($this->getOrganizations((int)$gCurrentUserId, (int)$gCurrentOrgId) as $organization) {
            if ($organization['shortname'] === $shortname) {
                $target = $organization;
            }
        }
        if ($target === null || !$target['member']) {
            throw new Exception('PLG_DEVTOOLS_ORGANIZATION_NOT_MEMBER');
        }
        if ($target['current']) {
            return;
        }

        $gCurrentSession->setValue('ses_org_id', $target['id']);
        $gCurrentSession->save();

        /*
         * A session of a "remember me" login takes its organization from the auto-login record
         * (Session::getOrganizationId()), and the next request would write that one back into the
         * session. The record moves along; the login dialog stores the chosen organization there
         * as well. The core offers no access to it, so it is read from the protected property.
         */
        $property = new ReflectionProperty(Session::class, 'mAutoLogin');
        $autoLogin = $property->isInitialized($gCurrentSession) ? $property->getValue($gCurrentSession) : null;
        if ($autoLogin instanceof AutoLogin && (int)$autoLogin->getValue('atl_id') > 0) {
            $autoLogin->setValue('atl_org_id', $target['id']);
            $autoLogin->save();
        }

        $gCurrentSession->initializeObjects();
    }
}

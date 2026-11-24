<?php
/**
 ***********************************************************************************************
 * DevTools - switch the language of the current session.
 *
 * Only the session of the administrator changes; the language of the organization and of every
 * other user stays as it is. It lasts until the session is reloaded.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use AdmidioPlugin\DevTools\DevTools;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getLanguage = admFuncVariableIsValid($_GET, 'language', 'string', array('requireValue' => true));

    if (!array_key_exists($getLanguage, $gL10n->getAvailableLanguages()) || !$gL10n->setLanguage($getLanguage)) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // back to the page the switch was used on, if it is a page of this installation
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    admRedirect(str_starts_with($referer, ADMIDIO_URL . '/') ? $referer : ADMIDIO_URL . '/');
} catch (Throwable $e) {
    handleException($e);
}

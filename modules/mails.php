<?php
/**
 ***********************************************************************************************
 * DevTools - the mails the mail catcher kept.
 *
 * Parameters:
 * id   : show this mail
 * mode : show (default), html (the HTML body for the preview frame), eml (download), delete_all (POST),
 *        settings (POST: mail_mode, redirect_address)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use AdmidioPlugin\DevTools\DevTools;
use AdmidioPlugin\DevTools\Mail\MailCatcher;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    DevTools::requireAccess();

    $getId = admFuncVariableIsValid($_GET, 'id', 'string');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('show', 'html', 'eml', 'delete_all', 'settings'), 'defaultValue' => 'show'));

    if ($getMode === 'settings') {
        DevTools::requirePost();
        $mailMode = (string)($_POST['mail_mode'] ?? '');
        if (!in_array($mailMode, array(MailCatcher::MODE_CATCH, MailCatcher::MODE_REDIRECT, MailCatcher::MODE_OFF), true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        DevTools::saveSetting('devtools_mail_mode', $mailMode);
        DevTools::saveSetting('devtools_mail_redirect_address', trim((string)($_POST['redirect_address'] ?? '')));
        DevTools::flash('success', $gL10n->get('SYS_SAVE_DATA'));
        admRedirect(DevTools::url('mails.php'));
    }

    if ($getMode === 'delete_all') {
        DevTools::requirePost();
        MailCatcher::deleteAll();
        admRedirect(DevTools::url('mails.php'));
    }

    $mail = $getId !== '' ? MailCatcher::read($getId) : null;

    if ($getMode === 'html' || $getMode === 'eml') {
        if ($mail === null) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }
        if ($getMode === 'html') {
            // Shown in a sandboxed frame without scripts; the policy keeps it from loading anything.
            header('Content-Type: text/html; charset=utf-8');
            header("Content-Security-Policy: default-src 'none'; img-src data: cid:; style-src 'unsafe-inline'; sandbox");
            echo (string)($mail['html'] ?? '');
            exit();
        }
        $raw = MailCatcher::readRaw($getId);
        if ($raw === null) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }
        header('Content-Type: message/rfc822');
        header('Content-Disposition: attachment; filename="' . $getId . '.eml"');
        echo $raw;
        exit();
    }

    $mails = array();
    if ($mail === null) {
        foreach (MailCatcher::list() as $entry) {
            $entry['link'] = DevTools::url('mails.php', array('id' => $entry['id']));
            $entry['recipients'] = implode(', ', array_slice($entry['envelope'] ?? $entry['originalRecipients'] ?? array(), 0, 5));
            $mails[] = $entry;
        }
    }

    $mode = $gSettingsManager->getString('devtools_mail_mode');
    $page = DevTools::createPage('mails.php', 'plugin.devtools.mails.tpl');
    $page->assignSmartyVariable('mailMode', $mode);
    $page->assignSmartyVariable('mailModes', array(
        MailCatcher::MODE_CATCH => $gL10n->get('PLG_DEVTOOLS_MAIL_MODE_CATCH'),
        MailCatcher::MODE_REDIRECT => $gL10n->get('PLG_DEVTOOLS_MAIL_MODE_REDIRECT'),
        MailCatcher::MODE_OFF => $gL10n->get('PLG_DEVTOOLS_MAIL_MODE_OFF')
    ));
    $page->assignSmartyVariable('redirectAddress', $gSettingsManager->getString('devtools_mail_redirect_address'));
    $page->assignSmartyVariable('settingsUrl', DevTools::url('mails.php', array('mode' => 'settings')));
    $page->assignSmartyVariable('mails', $mails);
    $page->assignSmartyVariable('mail', $mail);
    $page->assignSmartyVariable('listUrl', DevTools::url('mails.php'));
    $page->assignSmartyVariable('htmlUrl', $mail !== null ? DevTools::url('mails.php', array('id' => $getId, 'mode' => 'html')) : '');
    $page->assignSmartyVariable('emlUrl', $mail !== null ? DevTools::url('mails.php', array('id' => $getId, 'mode' => 'eml')) : '');
    $page->assignSmartyVariable('deleteUrl', DevTools::url('mails.php', array('mode' => 'delete_all')));
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}

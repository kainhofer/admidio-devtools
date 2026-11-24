<?php
namespace AdmidioPlugin\DevTools\Mail;

use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Utils\StringUtils;
use AdmidioPlugin\DevTools\DevTools;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 ***********************************************************************************************
 * Keeps the mails of a test installation from reaching real people.
 *
 * - **catch**: no mail leaves the system. The Email object of the sending is given a transport that
 *   stores the complete message instead of delivering it (CatchingSmtp). Copies to the sender go
 *   through the same object and are caught as well.
 * - **redirect**: the mail is delivered, but only to the configured address. The original
 *   recipients are kept in the stored record. Without a valid address it works like catch.
 * - **off**: nothing happens.
 *
 * Both work on the **email_recipients** filter, the one place every mail of Admidio passes before it
 * is sent. The filter is not handed the Email object, so the catcher finds it in the call stack.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class MailCatcher
{
    public const MODE_CATCH = 'catch';
    public const MODE_REDIRECT = 'redirect';
    public const MODE_OFF = 'off';

    public const MAX_MAILS = 500;

    /**
     * Callback of the **email_recipients** filter.
     * @param array<int,array<string,string>> $recipients
     * @return array<int,array<string,string>>
     */
    public static function filterRecipients(array $recipients, string $subject = ''): array
    {
        global $gSettingsManager;

        $mode = $gSettingsManager->getString('devtools_mail_mode');
        $address = trim($gSettingsManager->getString('devtools_mail_redirect_address'));
        if ($mode === self::MODE_OFF) {
            return $recipients;
        }

        $email = self::findEmailObject();

        if ($mode === self::MODE_REDIRECT && StringUtils::strValidCharacters($address, 'email')) {
            self::storeRecord(array(
                'mode' => self::MODE_REDIRECT,
                'subject' => $subject,
                'from' => $email?->From ?? '',
                'fromName' => $email?->FromName ?? '',
                'envelope' => array($address),
                'originalRecipients' => self::describeRecipients($recipients)
            ), null);

            return array(array(
                'email' => $address,
                'name' => 'DevTools (' . count($recipients) . ')',
                'firstname' => 'DevTools',
                'surname' => (string)count($recipients)
            ));
        }

        if ($email === null) {
            // Without the object the mail cannot be caught completely; it must not go out either.
            self::storeRecord(array(
                'mode' => self::MODE_CATCH,
                'subject' => $subject,
                'envelope' => array(),
                'originalRecipients' => self::describeRecipients($recipients),
                'note' => 'The mail object was not found; only subject and recipients are known.'
            ), null);
            return array();
        }

        $email->isSMTP();
        $email->setSMTPInstance(new CatchingSmtp($email));
        return $recipients;
    }

    /**
     * The Email object whose sendEmail() dispatched the filter.
     */
    private static function findEmailObject(): ?PHPMailer
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 15) as $frame) {
            if (isset($frame['object']) && $frame['object'] instanceof Email) {
                return $frame['object'];
            }
        }
        return null;
    }

    /**
     * @param array<int,array<string,string>> $recipients
     * @return array<int,string>
     */
    private static function describeRecipients(array $recipients): array
    {
        return array_map(static fn(array $recipient): string => trim(($recipient['name'] ?? '') . ' <' . ($recipient['email'] ?? '') . '>'), $recipients);
    }

    /**
     * Called by CatchingSmtp with the complete message.
     * @param array<int,string> $envelopeRecipients
     */
    public static function storeCaught(PHPMailer $mailer, string $envelopeFrom, array $envelopeRecipients, string $message): void
    {
        $attachments = array();
        foreach ($mailer->getAttachments() as $attachment) {
            $attachments[] = (string)($attachment[2] ?? $attachment[1] ?? '');
        }

        // PHPMailer switches an HTML mail with a text alternative to multipart/alternative while sending
        $isHtml = $mailer->ContentType !== PHPMailer::CONTENT_TYPE_PLAINTEXT;
        $addresses = static fn(array $list): array => array_map(static fn(array $entry): string => trim($entry[1] . ' <' . $entry[0] . '>'), $list);

        self::storeRecord(array(
            'mode' => self::MODE_CATCH,
            'subject' => $mailer->Subject,
            'from' => $mailer->From,
            'fromName' => $mailer->FromName,
            'envelopeFrom' => $envelopeFrom,
            'envelope' => $envelopeRecipients,
            'to' => $addresses($mailer->getToAddresses()),
            'cc' => $addresses($mailer->getCcAddresses()),
            'bcc' => $addresses($mailer->getBccAddresses()),
            'html' => $isHtml ? $mailer->Body : '',
            'text' => $isHtml ? $mailer->AltBody : $mailer->Body,
            'attachments' => $attachments
        ), $message);
    }

    /**
     * @param array<string,mixed> $record
     */
    private static function storeRecord(array $record, ?string $rawMessage): void
    {
        try {
            $directory = DevTools::dataPath('mails');
            $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $record = array('id' => $id, 'time' => date('c'), 'url' => defined('CURRENT_URL') ? CURRENT_URL : 'CLI') + $record;
            $record['hasRaw'] = $rawMessage !== null;

            if ($rawMessage !== null) {
                file_put_contents($directory . '/' . $id . '.eml', $rawMessage);
            }
            file_put_contents($directory . '/' . $id . '.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

            $files = glob($directory . '/*.json') ?: array();
            rsort($files, SORT_STRING);
            foreach (array_slice($files, self::MAX_MAILS) as $old) {
                @unlink($old);
                @unlink(substr($old, 0, -5) . '.eml');
            }
        } catch (Throwable) {
            // storing is a convenience; the mail is kept from going out anyway
        }
    }

    /**
     * The stored mails, newest first, without their bodies.
     * @return array<int,array<string,mixed>>
     */
    public static function list(): array
    {
        $mails = array();
        $files = glob(DevTools::dataPath('mails') . '/*.json') ?: array();
        rsort($files, SORT_STRING);
        foreach ($files as $file) {
            $mail = json_decode((string)file_get_contents($file), true);
            if (is_array($mail)) {
                unset($mail['html'], $mail['text']);
                $mails[] = $mail;
            }
        }
        return $mails;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function read(string $id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $file = DevTools::dataPath('mails') . '/' . $id . '.json';
        $mail = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        return is_array($mail) ? $mail : null;
    }

    public static function readRaw(string $id): ?string
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $file = DevTools::dataPath('mails') . '/' . $id . '.eml';
        return is_file($file) ? (string)file_get_contents($file) : null;
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $id) === 1;
    }

    public static function deleteAll(): void
    {
        foreach (array('json', 'eml') as $extension) {
            foreach (glob(DevTools::dataPath('mails') . '/*.' . $extension) ?: array() as $file) {
                @unlink($file);
            }
        }
    }
}

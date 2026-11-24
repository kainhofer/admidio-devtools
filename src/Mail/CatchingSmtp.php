<?php
namespace AdmidioPlugin\DevTools\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 ***********************************************************************************************
 * An SMTP "connection" that accepts every mail and hands it to the mail catcher instead of
 * delivering it. PHPMailer is given this object with setSMTPInstance(); it then believes it is
 * connected and talks SMTP to it as to a server: MAIL FROM, RCPT TO for every To, Cc and Bcc
 * address, DATA with the complete message.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class CatchingSmtp extends SMTP
{
    private string $envelopeFrom = '';
    /** @var array<int,string> */
    private array $envelopeRecipients = array();

    public function __construct(private readonly PHPMailer $mailer)
    {
    }

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        return true;
    }

    public function connected()
    {
        return true;
    }

    public function hello($host = '')
    {
        return true;
    }

    public function startTLS()
    {
        return true;
    }

    public function close()
    {
        $this->reset();
    }

    public function quit($close_on_error = true)
    {
        return true;
    }

    public function mail($from)
    {
        $this->envelopeFrom = (string)$from;
        $this->envelopeRecipients = array();
        return true;
    }

    public function recipient($address, $dsn = '')
    {
        $this->envelopeRecipients[] = (string)$address;
        return true;
    }

    public function data($msg_data)
    {
        MailCatcher::storeCaught($this->mailer, $this->envelopeFrom, $this->envelopeRecipients, (string)$msg_data);
        return true;
    }

    public function reset()
    {
        $this->envelopeFrom = '';
        $this->envelopeRecipients = array();
        return true;
    }

    public function getServerExt($name)
    {
        // the catcher understands everything a real server could, UTF-8 addresses included
        return true;
    }

    public function getLastTransactionID()
    {
        return 'devtools';
    }

    public function getError()
    {
        return array('error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => '');
    }
}

<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use AdmidioPlugin\DevTools\Mail\CatchingSmtp;
use AdmidioPlugin\DevTools\Mail\MailCatcher;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/autoload.php';

final class MailCatcherTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ADMIDIO_PATH')) {
            define('ADMIDIO_PATH', dirname(__DIR__, 4));
        }
        if (!defined('FOLDER_DATA')) {
            // never the adm_my_files of the checkout, which is a real installation
            define('FOLDER_DATA', '/.phpunit.cache/devtools-data');
        }
    }

    protected function tearDown(): void
    {
        MailCatcher::deleteAll();
    }

    public function testCatchingTransportKeepsTheCompleteMail(): void
    {
        MailCatcher::deleteAll();

        $mailer = new PHPMailer(true);
        $mailer->setFrom('admin@example.org', 'Admin');
        $mailer->addAddress('member@example.org', 'Member');
        $mailer->addBCC('hidden@example.org');
        $mailer->Subject = 'Test subject';
        $mailer->isHTML(true);
        $mailer->Body = '<p>Hello <b>world</b></p>';
        $mailer->AltBody = 'Hello world';
        $mailer->addStringAttachment('content', 'note.txt');
        $mailer->isSMTP();
        $mailer->Host = 'smtp.invalid';
        $mailer->setSMTPInstance(new CatchingSmtp($mailer));

        $this->assertTrue($mailer->send());

        $mails = MailCatcher::list();
        $this->assertCount(1, $mails);
        $mail = MailCatcher::read($mails[0]['id']);
        $this->assertSame('Test subject', $mail['subject']);
        $this->assertSame(array('member@example.org', 'hidden@example.org'), $mail['envelope']);
        $this->assertSame('<p>Hello <b>world</b></p>', $mail['html']);
        $this->assertSame('Hello world', $mail['text']);
        $this->assertSame(array('note.txt'), $mail['attachments']);
        $this->assertStringContainsString('Subject: Test subject', (string)MailCatcher::readRaw($mails[0]['id']));
        $this->assertNull(MailCatcher::read('../../etc/passwd'));
    }
}

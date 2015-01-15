<?php
use Vda\Smtp\Mailer;
use Vda\Smtp\Smtp;

class MailerTestClass extends PHPUnit_Framework_TestCase
{
    public function testSmtpClassLoading()
    {
        $mailer = new Mailer(new Smtp('localhost'));
        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    public function testSend()
    {
        $mailer = new Mailer(new Smtp('localhost'));
        $mailer->from('foo@example.com');
        $mailer->to('bar@example.com');
        $mailer->subject('Test');
        $mailer->text('Test');
        $this->assertTrue($mailer->send());
    }

    public function testNormalizeHeader()
    {
        $mailer = new Mailer(new Smtp('localhost'));
        $this->assertEquals('Content-Type', $mailer->normalizeHeader('Content-Type'));
        $this->assertEquals('Content-Type', $mailer->normalizeHeader('content-type'));
        $this->assertEquals('Content-Type', $mailer->normalizeHeader('content-Type'));
        $this->assertEquals('Content-Type', $mailer->normalizeHeader('content-type '));
        $this->assertEquals('Content-Type', $mailer->normalizeHeader(' Content-type'));
    }

    public function testDuplicateHeaders()
    {
        $mailer = new Mailer(new Smtp('localhost'));

        $mailer->from('foo@example.com');
        $mailer->header('From', 'Some Name <foo@example.com>');

        $mailer->to('bar@example.com');
        $mailer->header('To', 'Some Name <bar@example.com>');

        $mailer->replyTo('reply@to.com');
        $mailer->header('Reply-To', 'Some Name <reply@to.com>');

        $mailer->cc('cc@test.com');
        $mailer->header('Cc', 'Some Name <cc@test.com>');

        $mailer->bcc('bcc@test.com');
        $mailer->header('Bcc', 'Some Name <bcc@test.com>');

        $mailer->priority(1);
        $mailer->header('X-Priority', 1);

        $mailer->header('Date', date('Y-m-d H:i:s'));

        $mailer->subject('Test');
        $mailer->header('subject', 'Test');

        $mailer->text('Test');
        $this->assertTrue($mailer->send());

        $body = $mailer->getLastMessageBody();
        $this->assertEquals(1, preg_match_all('~^From: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^To: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^Reply-To: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^Cc: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^Bcc: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^X-Priority: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^Date: ~m', $body));
        $this->assertEquals(1, preg_match_all('~^Subject: ~m', $body));
    }
}

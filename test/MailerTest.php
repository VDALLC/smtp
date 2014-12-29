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
}

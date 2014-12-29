<?php
use Vda\Smtp\Smtp;

class SmtpTestClass extends PHPUnit_Framework_TestCase
{
    public function testSmtpClassLoading()
    {
        $smtp = new Smtp('localhost');
        $this->assertInstanceOf(Smtp::class, $smtp);
    }

    public function testSend()
    {
        $smtp = new Smtp('localhost');
        $res = $smtp->send('foo@example.com', 'bar@example.com', 'Test');
        $this->assertStringStartsWith('250 ', $res);
    }
}

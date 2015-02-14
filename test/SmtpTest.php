<?php
use Vda\Smtp\Smtp;

class ErroneousSmtp extends Smtp
{
    public $generateException = true;
    protected $_lastCommand;

    protected function _dialog($request, $expect)
    {
        $this->_lastCommand = $request;
        return parent::_dialog($request, $expect);
    }

    protected function _readResponse($expected)
    {
        $res = parent::_readResponse($expected);
        if ($this->generateException && preg_match('~^MAIL FROM:~', $this->_lastCommand)) {
            throw new Exception("Unexpected response. Expected {$expected}. Here is the dialog dump:\n{$this->_log}");
        }
        return $res;
    }
}

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

    /**
     * Test issuing RSET command.
     *
     * Without RSET after connect() you will get:
     *  Exception: Unexpected response. Expected 250. Here is the dialog dump:
     *  MAIL FROM: <foo@example.com>
     *  503 Sender already specified
     */
    public function testErroneousSmtp()
    {
        $smtp = new ErroneousSmtp('localhost');
        try {
            // generate exception
            $smtp->send('foo@example.com', 'bar@example.com', 'Test');
        } catch (Exception $ex) {
            // then verify send is successful after exception
            $smtp->generateException = false;
            $res = $smtp->send('foo@example.com', 'bar@example.com', 'Test');
            $this->assertStringStartsWith('250 ', $res);
        }
    }
}

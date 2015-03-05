<?php
use Vda\Smtp\Smtp;

class PipelinedSmtp extends Smtp
{
    public $generateException = true;

    public function connect()
    {
        parent::connect();
        $this->_pipelining = true;
    }

    protected function _dialog($request, $expect)
    {
        if ($this->generateException && $request == 'DATA') {
            // force error on DATA after invalid RCPT TO: (DevNullSmtp.jar specific:
            // DevNullSmtp.jar accepts DATA command after single invalid RCPT TO:)
            $request = 'DATAAA';
        }
        return parent::_dialog($request, $expect);
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

    public function testSingleDot()
    {
        $smtp = new Smtp('localhost');

        $res = $smtp->send('foo@example.com', 'bar@example.com', "Test\n.\nQwe");
        $this->assertStringStartsWith('250 ', $res);

        $res = $smtp->send('foo@example.com', 'bar@example.com', "Test\n\r.");
        $this->assertStringStartsWith('250 ', $res);

        $res = $smtp->send('foo@example.com', 'bar@example.com', ".\rQwe");
        $this->assertStringStartsWith('250 ', $res);

        $res = $smtp->send('foo@example.com', 'bar@example.com', "Test");
        $this->assertStringStartsWith('250 ', $res);
    }

    /**
     * Test issuing RSET command.
     *
     * Without RSET after connect() you will get:
     *  Exception: Unexpected response. Expected 250. Here is the dialog dump:
     *  MAIL FROM: <foo@example.com>
     *  503 Sender already specified
     *
     * @expectedException Exception
     * @expectedExceptionMessage Ensure exception raised
     */
    public function testErroneousSmtp()
    {
        $smtp = new Smtp('localhost');
        try {
            // generate exception
            $smtp->send('foo@example.com', 'qwe', 'Test');
        } catch (Exception $ex) {
            // then verify send is successful after exception
            $res = $smtp->send('foo@example.com', 'bar@example.com', 'Test');
            $this->assertStringStartsWith('250 ', $res);

            throw new Exception('Ensure exception raised');
        }
    }

    /**
     * Test reading all pipelined results in case of exception in the middle.
     *
     * Without reading all _pipelinedCommands results in _dialog you will get timeout and:
     *  Unexpected response . Expected 250. Here is the dialog dump:
     *  RSET
     *  MAIL FROM: <foo@example.com>
     *  RCPT TO: <bar@example.com>
     *  DATA
     *  250 OK
     *  354 Start mail input; end with <CRLF>.<CRLF>
     *  QUIT
     *
     * @expectedException Exception
     * @expectedExceptionMessage Ensure exception raised
     */
    public function testErroneousPipeliningSmtp()
    {
        $smtp = new PipelinedSmtp('localhost');
        try {
            // generate exception
            $smtp->send('foo@example.com', 'qwe', 'Test');
        } catch (Exception $ex) {
            // then verify send is successful after exception
            $smtp->generateException = false;
            $res = $smtp->send('foo@example.com', 'bar@example.com', 'Test');
            $this->assertStringStartsWith('250 ', $res);

            throw new Exception('Ensure exception raised');
        }
    }
}

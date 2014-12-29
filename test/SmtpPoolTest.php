<?php
use Vda\Smtp\SmtpPool;

class Acc
{
    public $log = '';

    public function log($str)
    {
        $this->log .= $str . "\n";
    }
}

class TestSmtp implements \Vda\Smtp\ISmtp
{
    protected $log;
    protected $prefix;

    public function __construct(Acc $acc, $prefix)
    {
        $this->log = $acc;
        $this->prefix = $prefix;
    }

    public function send($from, $to, $data)
    {
        $this->log->log("{$this->prefix}::send({$from}, {$to})");
    }

    public function disconnect()
    {
    }
}

class SmtpPoolTestClass extends PHPUnit_Framework_TestCase
{
    public function testSmtpPoolRoundRobin()
    {
        $acc = new Acc();

        $pool = new SmtpPool('localhost:25,localhost:26,localhost:27', function($host, $port) use ($acc) {
            return new TestSmtp($acc, $host . ':' . $port);
        });

        for ($i = 1 ; $i <= 6 ; $i++) {
            $pool->send('a@g.com', 'b@g.com', 'test');
        }

        $expectedLog = <<<LOG
localhost:25::send(a@g.com, b@g.com)
localhost:26::send(a@g.com, b@g.com)
localhost:27::send(a@g.com, b@g.com)
localhost:25::send(a@g.com, b@g.com)
localhost:26::send(a@g.com, b@g.com)
localhost:27::send(a@g.com, b@g.com)

LOG;

        $this->assertEquals($expectedLog, $acc->log);
    }
}

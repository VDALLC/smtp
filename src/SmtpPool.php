<?php
namespace Vda\Smtp;

use Exception;
use Vda\Smtp\Exception\ConnectionException;

class SmtpPool implements ISmtp
{
    protected $specs = array();

    /**
     * @var ISmtp[]
     */
    protected $instances = array();

    protected $maxTries;

    protected $index = -1;

    /**
     * @var callable
     */
    protected $smtpFactory;

    public function __construct($smtpList, $factory = null)
    {
        $this->smtpFactory = $factory;

        foreach (explode(',', $smtpList) as $smtpSpec) {
            $smtpSpec = explode(':', trim($smtpSpec));
            if (empty($smtpSpec[0])) {
                throw new Exception('Smtp host must be specified. Smtp list is "' . $smtpList . '"');
            }
            if (empty($smtpSpec[1])) {
                $smtpSpec[1] = 25;
            }

            $this->specs[] = array(
                'host'  => $smtpSpec[0],
                'port'  => $smtpSpec[1],
            );
        }

        $this->maxTries = count($this->specs);
    }

    /**
     * @return ISmtp
     */
    protected function getInstance()
    {
        // round-robin
        $this->index = ($this->index + 1) % count($this->specs);

        if (empty($this->instances[$this->index])) {
            $host = $this->specs[$this->index]['host'];
            $port = $this->specs[$this->index]['port'];
            if (is_callable($this->smtpFactory)) {
                $this->instances[$this->index] = call_user_func($this->smtpFactory, $host, $port);
            } else {
                $this->instances[$this->index] = new Smtp($host, $port);
            }
        }
        return $this->instances[$this->index];
    }

    public function send($from, $to, $data)
    {
        $tries = $this->maxTries;
        while ($tries) {
            try {
                $this->getInstance()->send($from, $to, $data);
                return;
            } catch (ConnectionException $ex) {
                $tries--;
                if ($tries == 0) {
                    throw $ex;
                }
            }
        }
    }

    public function disconnect()
    {
        foreach ($this->instances as $instance) {
            $instance->disconnect();
        }
        $this->instances = array();
    }
}

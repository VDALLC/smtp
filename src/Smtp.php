<?php
/**
 * SMTP
 *
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     SMTP
 * @author      Sergio Vaccaro <sergiovaccaro67@gmail.com>
 * @copyright   Copyright (c) Sergio Vaccaro
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt     GPLv3
 */

namespace Vda\Smtp;

use Exception;
use Vda\Smtp\Exception\ConnectionException;

/**
 * Rich SMTP client
 *
 * @package     SMTP
 * @link        http://en.wikipedia.org/wiki/Simple_Mail_Transfer_Protocol Documentation
 */
class Smtp implements ISmtp
{
    /**
     * SMTP socket resource
     *
     * @var resource
     */
    protected $_smtp = null;

    /**
     * Server
     *
     * @var array
     */
    protected $_server = array(
        'host'      => null,
        'port'      => 25,
        'timeout'   => 3,
    );

    /**
     * Auth user (base64 encoded)
     *
     * @var string
     */
    protected $_user;

    /**
     * Auth pass (base64 encoded)
     *
     * @var string
     */
    protected $_pass;

    /**
     * Log
     *
     * @var string
     */
    protected $_log = '';

    protected $_pipelining = true;

    protected $_pipelinedCommands = array();

    /**
     * Connection to the SMTP server
     *
     * @param string $host
     * @param integer $port
     * @param integer $timeout
     * @throws Exception
     */
    public function __construct($host, $port = 25 , $timeout = 3)
    {
        // Avoid a warning
        if (empty($host)) {
            throw new ConnectionException('Undefined SMTP server');
        }

        // Settings
        $this->_server['host'] = (string)$host;
        if ($port) {
            $this->_server['port'] = (integer)$port;
        }
        if ($timeout) {
            $this->_server['timeout'] = (integer)$timeout;
        }
    }

    /**
     * Connection to the SMTP server
     * @throws Exception
     */
    public function connect()
    {
        // Connect (if not already connected)
        if (empty($this->_smtp)) {
            if ($this->_smtp = fsockopen($this->_server['host'], $this->_server['port'], $errno, $errstr, $this->_server['timeout'])) {
                if (substr($response = fgets($this->_smtp), 0, 3) != self::READY) {
                    $this->close();
                    throw new ConnectionException('Server NOT ready! The server responded with this message:' . PHP_EOL . $response);
                }

                $this->_log = $response . PHP_EOL;

                // EHLO
                $ehlo = $this->_dialog('EHLO todo', self::OK);
                $this->_pipelining = preg_match('~250[\s-]pipelining~i', $ehlo);

                // Auth
                if ($this->_user && $this->_pass) {
                    // See http://www.fehcom.de/qmail/smtpauth.html
                    $this->_dialog('auth login', self::TEXT64);
                    $this->_dialog($this->_user, self::TEXT64);
                    $this->_dialog($this->_pass, self::AUTHOK);
                }
            } else {
                $this->_smtp = null;
                $message = 'Unable to connect to ' . $this->_server['host'] . ' on port ' . $this->_server['port'] . ' within ' . $this->_server['timeout'] . ' seconds' . PHP_EOL;
                if (!empty($errstr)) {
                    $message .= 'The remote server responded:' . PHP_EOL . $errstr . '(' . $errno . ')';
                }
                throw new ConnectionException($message);
            }
        }
    }

    /**
     * @param $expected
     * @param null|Exception $prevException used in collecting all smtp responses in pipelining mode.
     * @return string
     * @throws Exception
     */
    protected function _readResponse($expected, $prevException = null)
    {
        $response = '';
        while (($line = fgets($this->_smtp)) !== false) {
            $response .= $line;
            if ($line[3] != '-') {
                break;
            }
        }
        $this->_log .= $response;

        if (substr($response, 0, 3) != $expected) {
            $type = gettype($response);
            $msg = "Unexpected response {$type}:'{$response}'. ";
            if (feof($this->_smtp)) {
                $this->close();
                $msg .= 'EOF - true. ';
            } else {
                $msg .= 'EOF - false. ';
                if ($response == '') {
                    $msg .= 'Timeout? Reconnecting. ';
                    $this->close();
                }
            }
            $msg .= "Expected {$expected}. Here is the dialog dump:\n{$this->_log}";
            throw new Exception($msg, 0, $prevException);
        }

        return $response;
    }

    /**
     * Perform a request/response exchange
     *
     * @param string $request
     * @param string $expect The expected status code
     * @return string
     * @throws Exception
     */
    protected function _dialog($request, $expect)
    {
        $this->_log .= $request . PHP_EOL;

        fwrite($this->_smtp, $request . self::NL);

        if ($this->_pipelining) {
            // is pipelinable command?
            if (in_array(substr($request, 0, 4), array('RSET', 'MAIL', 'SEND', 'SOML', 'SAML', 'RCPT'))) {
                $this->_pipelinedCommands[] = $expect;
                return null;
            } else {
                $exception = null;
                while ($this->_pipelinedCommands) {
                    $_expected = array_shift($this->_pipelinedCommands);
                    try {
                        $this->_readResponse($_expected, $exception);
                    } catch (Exception $ex) {
                        // Do not break while here
                        // Ensure all results are read in case of exception in the middle
                        $exception = $ex;
                    }
                }
                if ($exception) {
                    // Ensure last result are also read in case of exception in the middle
                    $this->_readResponse($expect, $exception);
                    // live above may generate exception (for ex. smtp server will not
                    // accept DATA command after single invalid RCPT TO:), but to be sure
                    // throw exception as final statement.
                    throw $exception;
                }
            }
        }

        return $this->_readResponse($expect);
    }

    protected function close()
    {
        if ($this->_smtp) {
            fclose($this->_smtp);
            $this->_smtp = null;
        }
    }

    public function disconnect()
    {
        if ($this->_smtp) {
            try {
                $this->_dialog('QUIT', self::BYE);
            } catch (Exception $ex) {}
            $this->close();
        }
    }

    /**
     * Closes connection
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Auth
     *
     * Auth login implementation.
     * Consider that there are many auth types.
     *
     * @param string $user
     * @param string $pass
     */
    public function auth($user, $pass)
    {
        $this->_user = base64_encode($user);
        $this->_pass = base64_encode($pass);
    }

    /**
     * Send
     *
     * @param $from
     * @param $to
     * @param $data
     * @return string some string like "250 769947 message accepted for delivery"
     */
    public function send($from, $to, $data)
    {
        $this->_log = '';

        $this->connect();
        $this->_dialog('RSET', self::OK);

        $this->_dialog("MAIL FROM: <{$from}>", self::OK);

        foreach ((array)$to as $each) {
            $this->_dialog("RCPT TO: <{$each}>", self::OK);
        }

        $this->_dialog('DATA', self::DATAOK);
        $message = $data . self::NL . '.'; // The _dialog function below will add self::NL;
        return $this->_dialog($message, self::OK);
    }

    /**
     * Dump the log
     *
     * @return string
     */
    public function dump()
    {
        return $this->_log;
    }
}

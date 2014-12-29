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
 * @version     1.8
 */

namespace Vda\Smtp;

use Exception;

/**
 * Rich SMTP client
 *
 * @package     SMTP
 * @link        http://en.wikipedia.org/wiki/Simple_Mail_Transfer_Protocol Documentation
 */
class Mailer
{
    /**
     * Charset (excluding text and attachments)
     *
     * @var string
     */
    protected $_charset = 'UTF-8';

    /**
     * From
     *
     * @var array
     */
    protected $_from;

    /**
     * Reply-to
     *
     * @var array
     */
    protected $_replyTo = array();

    /**
     * To
     *
     * Multiple recipients allowed
     *
     * @var array
     */
    protected $_to = array();

    /**
     * Cc
     *
     * Multiple recipients allowed
     *
     * @var array
     */
    protected $_cc = array();

    /**
     * Bcc
     *
     * Multiple recipients allowed
     *
     * @var array
     */
    protected $_bcc = array();

    /**
     * Priority
     *
     * Priorities are from 1 (low) to 5 (high)
     * 3 is normal
     *
     * @var integer
     */
    protected $_priority;

    /**
     * Custom headers
     *
     * @var array
     */
    protected $_headers = array();

    /**
     * Subject
     *
     * @var string
     */
    protected $_subject;

    /**
     * Text message
     *
     * @var string
     */
    protected $_text = array(
        'body'          => '',
        'Content-Type'  => 'text/plain',
        'charset'       => 'UTF-8'
    );

    /**
     * File attachments
     *
     * @var array
     */
    protected $_attachments = array();

    /**
     * Raw attachments
     *
     * @var array
     */
    protected $_raw = array();

    protected $smtp;

    public function __construct(ISmtp $smtp)
    {
        $this->smtp = $smtp;
    }

    /**
     * Charset encoding
     *
     * @see http://www.pcvr.nl/tcpip/smtp_sim.htm
     * @param string $string
     * @return string
     */
    protected function _encode($string)
    {
        if ($this->_charset) {
            return '=?' . $this->_charset . '?B?' . base64_encode($string) . '?=';
        } else {
            return $string;
        }
    }

    /**
     * Add or replace recipients
     *
     * @param string $dest
     * @param string $destName
     * @param array $class
     * @throws Exception
     */
    protected function _recipients($dest, $destName, $class)
    {
        if (in_array($class, array('_to', '_cc', '_bcc')))
        {
            if ($destName)
            {
                if ($dest)
                    $this->{$class}[$destName] = $dest;
                else
                {
                    if (isset($this->{$class}[$destName]))
                        unset($this->{$class}[$destName]);
                }
            }
            else
            {
                if ($dest)
                    $this->{$class}[] = $dest;
            }
        }
        else
            throw new Exception('Wrong recipient');
    }

    /**
     * Charset (excluding text and attachments)
     * Note that this is the charset for Subject, names, etc.
     * In a web context, should match the 'Content-Type'.
     * If empty will be pure ASCII (7-bit)
     *
     * @param string
     */
    public function charset($charset)
    {
        $this->_charset = (string) $charset;
    }

    /**
     * From
     *
     * @param string $from
     * @param string $name
     * @return array
     */
    public function from($from = null, $name = '')
    {
        if (null !== $from)
        {
            $this->_from['address'] = (string) $from;
            $this->_from['name'] = (string) $name;
        }
        return $this->_from;
    }

    /**
     * Reply-to
     *
     * @param string $reply_to
     * @param string $name
     * @return array
     */
    public function replyTo($reply_to = null, $name = null)
    {

        if (null !== $reply_to)
        {
            $this->_replyTo['address'] = (string) $reply_to;
            $this->_replyTo['name'] = (string) $name;
        }
        return $this->_replyTo;
    }

    /**
     * To
     *
     * @param string $to
     * @param string $toName
     * @return array
     */
    public function to($to = null, $toName = '')
    {
        $this->_recipients($to, $toName, '_to');
        return $this->_to;
    }

    /**
     * Cc
     *
     * @param string $cc
     * @param string $ccName
     * @return array
     */
    public function cc($cc = null, $ccName = '')
    {
        $this->_recipients($cc, $ccName, '_cc');
        return $this->_cc;
    }

    /**
     * Bcc
     *
     * @param string $bcc
     * @param string $bccName
     * @return array
     */
    public function bcc($bcc = null, $bccName = '')
    {
        $this->_recipients($bcc, $bccName, '_bcc');
        return $this->_bcc;
    }

    /**
     * Priority
     *
     * @param integer $priority
     * @throws Exception
     * @return integer
     */
    public function priority($priority = null)
    {
        if ($priority)
        {
            $priority = (integer) $priority;
            if (($priority > 0) && ($priority < 6))
                $this->_priority = $priority;
            else
                throw new Exception('Priority are integer from 1 (low) to 5 (high)');
        }
        return $this->_priority;
    }

    /**
     * Custom header
     *
     * @param string $name
     * @param string $value
     * @return array
     */
    public function header($name = null, $value = null)
    {
        if ($name)
            $this->_headers[(string) $name] = (string) $value;
        return $this->_headers;
    }

    /**
     * Subject
     *
     * @param string $subject
     * @return string
     */
    public function subject($subject = null)
    {
        if (null !== $subject)
            $this->_subject = (string) $subject;
        return $this->_subject;
    }

    /**
     * Text
     *
     * @param string $text
     * @param string $content_type
     * @param string $charset
     * @return string
     */
    public function text($text = null, $content_type = 'text/plain', $charset = 'utf-8')
    {
        if (null !== $text)
        {
            $this->_text = array(
                'body'          => str_replace("\n", ISmtp::NL, (string) $text),
                'Content-Type'  => $content_type,
                'charset'       => $charset
            );
        }
        return $this->_text;
    }

    /**
     * Attachment from file
     *
     * @link http://en.wikipedia.org/wiki/MIME#Content-Transfer-Encoding
     * @link http://en.wikipedia.org/wiki/MIME#Multipart_messages
     * @link http://support.mozilla.org/it/questions/746116
     * @param string $path
     * @param string $name
     * @param string $content_type
     * @param string $charset Will be used for text/* only
     * @throws Exception
     * @return array
     */
    public function attachment($path = null, $name = '', $content_type = 'application/octet-stream', $charset = 'utf-8')
    {
        if (is_readable($path))
        {
            $attachment = array(
                'path'          => (string) $path,
                'Content-Type'  => (string) $content_type,
                'charset'       => (string) $charset
            );

            $name || ($name = pathinfo($path, PATHINFO_BASENAME));

            $this->_attachments[$name] = $attachment;
        }
        elseif(!empty($path))
            throw new Exception('File ' . $path . ' not found or not readable');

        return $this->_attachments;
    }

    /**
     * Raw attachment
     *
     * @link http://en.wikipedia.org/wiki/MIME#Content-Transfer-Encoding
     * @link http://en.wikipedia.org/wiki/MIME#Multipart_messages
     * @link http://support.mozilla.org/it/questions/746116
     * @param string $name
     * @param string $content
     * @param string $content_type
     * @param string $charset Will be used for text/* only
     * @return array
     */
    public function raw($content = null, $name = '', $content_type = 'text/plain', $charset = 'utf-8')
    {
        if ($content)
        {
            $attachment = array(
                'content'       => (string) $content,
                'Content-Type'  => (string) $content_type,
                'charset'       => (string) $charset
            );

            if (empty($name))
                $name = time() . '-' . mt_rand();
            $this->_raw[$name] = $attachment;
        }

        return $this->_raw;
    }

    /**
     * Completely clear recipients, attachments and headers (for a new message)
     */
    public function clear()
    {
        $this->_to = array();
        $this->_cc = array();
        $this->_bcc = array();
        $this->_headers = array();
        $this->_attachments = array();
        $this->_raw = array();
    }

    /**
     * Send
     *
     * @see http://www.pcvr.nl/tcpip/smtp_sim.htm
     * @return string
     * @throws Exception
     */
    public function send()
    {
        // Check for minimum requirements
        if (empty($this->_from))
            throw new Exception('Sender undefined');

        if (empty($this->_to) && empty($this->_cc) && empty($this->_bcc))
            throw new Exception('No recipients');

        if (empty($this->_subject)) // Net Ecology
            throw new Exception('No subject');

        if (empty($this->_text))
            throw new Exception('No message text');

        // Message
        $message = '';

        // From
        if (empty($this->_from['name']))
            $message .= 'From: <' . $this->_from['address'] . '>' . ISmtp::NL;
        else
            $message .= 'From: "' . $this->_encode($this->_from['name']) . '"<' . $this->_from['address'] . '>' . ISmtp::NL;

        // Reply to
        if (!empty($this->_replyTo))
        {
            if (empty($this->_replyTo['name']))
                $message .= 'Reply-To: <' . $this->_replyTo['address'] . '>' . ISmtp::NL;
            else
                $message .= 'Reply-To: "' . $this->_encode($this->_replyTo['name']) . '"<' . $this->_replyTo['address'] . '>' . ISmtp::NL;
        }

        // To
        foreach ($this->_to as $name => $rcpt)
        {
            if (is_integer($name))
                $message .= 'To: <' . $rcpt . '>' . ISmtp::NL;
            else
                $message .= 'To: "' . $this->_encode($name) . '"<' . $rcpt . '>' . ISmtp::NL;
        }

        // Cc
        foreach ($this->_cc as $name => $rcpt)
        {
            if (is_integer($name))
                $message .= 'Cc: <' . $rcpt . '>' . ISmtp::NL;
            else
                $message .= 'Cc: "' . $this->_encode($name) . '"<' . $rcpt . '>' . ISmtp::NL;
        }

        // Bcc
        foreach ($this->_bcc as $name => $rcpt)
        {
            if (is_integer($name))
                $message .= 'Bcc: <' . $rcpt . '>' . ISmtp::NL;
            else
                $message .= 'Bcc: "' . $this->_encode($name) . '"<' . $rcpt . '>' . ISmtp::NL;
        }

        // Priority
        if ($this->_priority)
            $message .= 'X-Priority: ' . $this->_priority . ISmtp::NL;

        // Custom headers
        foreach ($this->_headers as $name => $value)
            $message .= $name . ': ' . $value. ISmtp::NL;

        // Date
        $message .= 'Date: ' . date('r') . ISmtp::NL;

        // Subject
        $message .= 'Subject: ' . $this->_encode($this->_subject) . ISmtp::NL;

        // Message
        /*
        The message will contain text and attachments.
        This implementation consider the multipart/mixed method only.
        http://en.wikipedia.org/wiki/MIME#Multipart_messages
        */
        if ($this->_attachments || $this->_raw)
        {
            $separator = hash('sha256', time());
            $message .= 'MIME-Version: 1.0' . ISmtp::NL;
            $message .= 'Content-Type: multipart/mixed; boundary=' . $separator . ISmtp::NL;
            $message .= ISmtp::NL;
            $message .= 'This is a message with multiple parts in MIME format.' . ISmtp::NL;
            $message .= '--' . $separator . ISmtp::NL;
            $message .= 'Content-Type: ' . $this->_text['Content-Type'] . '; charset=' . $this->_text['charset'] . ISmtp::NL;
            $message .= ISmtp::NL;
            $message .= $this->_text['body'] . ISmtp::NL;
            foreach ($this->_attachments as $name => $attach)
            {
                $message .= '--' . $separator . ISmtp::NL;
                $message .= 'Content-Disposition: attachment; filename=' . $name . '; modification-date="' . date('r', filemtime($attach['path'])) . '"' . ISmtp::NL;
                if (substr($attach['Content-Type'], 0, 5) == 'text/')
                {
                    $message .= 'Content-Type: ' . $attach['Content-Type'] . '; charset=' . $attach['charset'] . ISmtp::NL;
                    $message .= ISmtp::NL;
                    $message .= file_get_contents($attach['path']) . ISmtp::NL;
                }
                else
                {
                    $message .= 'Content-Type: ' . $attach['Content-Type'] . ISmtp::NL;
                    $message .= 'Content-Transfer-Encoding: base64' . ISmtp::NL;
                    $message .= ISmtp::NL;
                    $message .= base64_encode(file_get_contents($attach['path'])) . ISmtp::NL;
                }
            }
            foreach ($this->_raw as $name => $raw)
            {
                $message .= '--' . $separator . ISmtp::NL;
                $message .= 'Content-Disposition: attachment; filename=' . $name . '; modification-date="' . date('r') . '"' . ISmtp::NL;
                if (substr($raw['Content-Type'], 0, 5) == 'text/')
                {
                    $message .= 'Content-Type: ' . $raw['Content-Type'] . '; charset=' . $raw['charset'] . ISmtp::NL;
                    $message .= ISmtp::NL;
                    $message .= $raw['content'] . ISmtp::NL;
                }
                else
                {
                    $message .= 'Content-Type: ' . $raw['Content-Type'] . ISmtp::NL;
                    $message .= 'Content-Transfer-Encoding: base64' . ISmtp::NL;
                    $message .= ISmtp::NL;
                    $message .= base64_encode($raw['content']) . ISmtp::NL;
                }
            }
            $message .= '--' . $separator . '--' . ISmtp::NL;
        }
        else
        {
            $message .= 'Content-Type: ' . $this->_text['Content-Type'] . '; charset=' . $this->_text['charset'] . ISmtp::NL;
            $message .= ISmtp::NL . $this->_text['body'] . ISmtp::NL;
        }

        $recipients = array_merge(
            array_values($this->_to),
            array_values($this->_cc),
            array_values($this->_bcc)
        );
        $res = $this->smtp->send($this->_from['address'], $recipients, $message);
        return substr($res, 0, 3) == ISmtp::OK;
    }
}

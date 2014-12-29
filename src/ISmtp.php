<?php
namespace Vda\Smtp;

interface ISmtp
{
    /**
     * New line character
     *
     * SMTP wants <CR><LF>
     */
    const NL = "\r\n";

    /**
     * Ready status code
     * @link http://www.greenend.org.uk/rjk/tech/smtpreplies.html
     */
    const READY = '220';

    /**
     * Ok status code
     * @link http://www.greenend.org.uk/rjk/tech/smtpreplies.html
     */
    const OK = '250';

    /**
     * Text encoded as base64
     * @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const TEXT64 = '334';

    /**
     * Auth OK
     * @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const AUTHOK = '235';

    /**
     * Data ok
     * @link @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const DATAOK = '354';

    /**
     * Bye
     * @link @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const BYE = '221';

    public function send($from, $to, $data);
}

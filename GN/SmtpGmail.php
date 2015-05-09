<?php
/**
 * @author Radosław Szczepaniak <radoslaw.szczepaniak@gammanet.pl>
 */

class GN_SmtpGmail extends GN_Smtp
{
    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $fullname = '';

    /**
     * @param string $host
     * @return bool
     */
    public function Hello($host = '')
    {
        $ret = parent::Hello($host);

        if ($ret) {
            fputs($this->smtp_conn, 'AUTH XOAUTH2 ' . base64_encode("user={$this->email}\1auth=Bearer {$this->token}\1\1") . $this->CRLF);
            $rply = $this->get_lines();
            $ret = substr($rply, 0, 3) == 235;
        }

        return $ret;
    }
}
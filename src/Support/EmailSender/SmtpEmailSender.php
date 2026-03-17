<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support\EmailSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;

/**
 * SMTP email sender — sends emails via SMTP socket connection.
 *
 * Configuration:
 *   - host: SMTP server hostname
 *   - port: SMTP server port (25, 465, 587)
 *   - username: SMTP auth username
 *   - password: SMTP auth password
 *   - fromEmail: Sender email address
 *   - fromName: Sender display name
 *   - encryption: 'tls', 'ssl' or '' (none)
 *
 * KPHP-compatible: uses fsockopen for SMTP connection, no PHPMailer.
  * @lphenom-build shared
 */
final class SmtpEmailSender implements CodeSenderInterface
{
    /** @var string */
    private string $host;

    /** @var int */
    private int $port;

    /** @var string */
    private string $username;

    /** @var string */
    private string $password;

    /** @var string */
    private string $fromEmail;

    /** @var string */
    private string $fromName;

    /** @var string */
    private string $encryption;

    public function __construct(
        string $host,
        int $port,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        string $encryption
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->username   = $username;
        $this->password   = $password;
        $this->fromEmail  = $fromEmail;
        $this->fromName   = $fromName;
        $this->encryption = $encryption;
    }

    public function send(string $recipient, string $code): bool
    {
        $subject = 'Your authentication code';
        $body    = 'Your authentication code: ' . $code . "\r\n\r\nThis code is valid for a limited time.";

        return $this->sendEmail($recipient, $subject, $body);
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        $host = $this->host;
        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $this->host;
        }

        $errno  = 0;
        $errstr = '';
        $socket = @fsockopen($host, $this->port, $errno, $errstr, 10);
        if ($socket === false) {
            return false;
        }

        // Read greeting
        $greeting = $this->readResponse($socket);
        if ($greeting === '') {
            fclose($socket);
            return false;
        }

        // EHLO
        if (!$this->sendCommand($socket, 'EHLO localhost', '250')) {
            fclose($socket);
            return false;
        }

        // STARTTLS if needed
        if ($this->encryption === 'tls') {
            if (!$this->sendCommand($socket, 'STARTTLS', '220')) {
                fclose($socket);
                return false;
            }
            $cryptoResult = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoResult !== true) {
                fclose($socket);
                return false;
            }
            if (!$this->sendCommand($socket, 'EHLO localhost', '250')) {
                fclose($socket);
                return false;
            }
        }

        // AUTH LOGIN
        if ($this->username !== '') {
            if (!$this->sendCommand($socket, 'AUTH LOGIN', '334')) {
                fclose($socket);
                return false;
            }
            if (!$this->sendCommand($socket, base64_encode($this->username), '334')) {
                fclose($socket);
                return false;
            }
            if (!$this->sendCommand($socket, base64_encode($this->password), '235')) {
                fclose($socket);
                return false;
            }
        }

        // MAIL FROM
        if (!$this->sendCommand($socket, 'MAIL FROM:<' . $this->fromEmail . '>', '250')) {
            fclose($socket);
            return false;
        }

        // RCPT TO
        if (!$this->sendCommand($socket, 'RCPT TO:<' . $to . '>', '250')) {
            fclose($socket);
            return false;
        }

        // DATA
        if (!$this->sendCommand($socket, 'DATA', '354')) {
            fclose($socket);
            return false;
        }

        // Headers + body
        $message = 'From: ' . $this->fromName . ' <' . $this->fromEmail . ">\r\n"
                 . 'To: ' . $to . "\r\n"
                 . 'Subject: ' . $subject . "\r\n"
                 . 'MIME-Version: 1.0' . "\r\n"
                 . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
                 . "\r\n"
                 . $body . "\r\n"
                 . '.';

        if (!$this->sendCommand($socket, $message, '250')) {
            fclose($socket);
            return false;
        }

        // QUIT
        $this->sendCommand($socket, 'QUIT', '221');
        fclose($socket);

        return true;
    }

    /**
     * @param resource $socket
     */
    private function sendCommand($socket, string $command, string $expectedCode): bool
    {
        fwrite($socket, $command . "\r\n");
        $response = $this->readResponse($socket);
        return substr($response, 0, strlen($expectedCode)) === $expectedCode;
    }

    /**
     * @param resource $socket
     */
    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // If the 4th char is a space, this is the last line
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}

<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support\EmailSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;

/**
 * KPHP-compatible email sender via HTTP API.
 *
 * Sends email verification codes through an HTTP API endpoint instead of
 * direct SMTP connection. Used in KPHP builds where fsockopen() and
 * stream_socket_enable_crypto() are not available.
 *
 * Compatible APIs (configure $apiUrl accordingly):
 *   - Mailgun:   https://api.mailgun.net/v3/{domain}/messages
 *   - SendGrid:  https://api.sendgrid.com/v3/mail/send
 *   - Any custom HTTP endpoint accepting POST with to/from/subject/text fields
 *
 * Configuration via constructor:
 *   - apiUrl:    Full URL of the HTTP email API endpoint
 *   - apiKey:    Bearer token or API key for Authorization header
 *   - fromEmail: Sender email address
 *   - subject:   Email subject (default: 'Код подтверждения')
 *
 * KPHP-compatible: uses file_get_contents + stream_context_create,
 * http_build_query, no fsockopen, no Closure.
 *
 * @lphenom-build kphp
 */
final class KphpHttpEmailSender implements CodeSenderInterface
{
    /** @var string */
    private string $apiUrl;

    /** @var string */
    private string $apiKey;

    /** @var string */
    private string $fromEmail;

    /** @var string */
    private string $subject;

    public function __construct(
        string $apiUrl,
        string $apiKey,
        string $fromEmail,
        string $subject = 'Код подтверждения'
    ) {
        $this->apiUrl    = $apiUrl;
        $this->apiKey    = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->subject   = $subject;
    }

    public function send(string $recipient, string $code): bool
    {
        /** @var array<string, string> $postData */
        $postData = [
            'to'      => $recipient,
            'from'    => $this->fromEmail,
            'subject' => $this->subject,
            'text'    => 'Ваш код подтверждения: ' . $code,
        ];

        $postBody = http_build_query($postData);

        /** @var array<string, string> $httpOpts */
        $httpOpts = [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
                       . 'Authorization: Bearer ' . $this->apiKey,
            'content' => $postBody,
            'timeout' => '10',
        ];

        /** @var array<string, array<string, string>> $opts */
        $opts = ['http' => $httpOpts];

        $context = stream_context_create($opts);

        $response = false;
        $exception = null;
        try {
            $response = file_get_contents($this->apiUrl, false, $context);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            return false;
        }

        return $response !== false;
    }
}


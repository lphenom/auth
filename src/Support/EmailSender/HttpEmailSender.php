<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support\EmailSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;

/**
 * HTTP API email sender — sends email verification codes via an HTTP API endpoint.
 *
 * Works identically in both shared (PHP) and kphp (compiled binary) builds.
 * Uses file_get_contents() + stream_context_create() which are available in both.
 *
 * Compatible APIs (configure $apiUrl accordingly):
 *   - Mailgun:    https://api.mailgun.net/v3/{domain}/messages
 *   - SendGrid:   https://api.sendgrid.com/v3/mail/send
 *   - Mailersend: https://api.mailersend.com/v1/email
 *   - Any custom HTTPS POST endpoint accepting to/from/subject/text fields
 *
 * Configuration via constructor:
 *   - apiUrl:    Full URL of the HTTP email API endpoint
 *   - apiKey:    Bearer token or API key for Authorization header
 *   - fromEmail: Sender email address
 *   - subject:   Email subject (default: 'Код подтверждения')
 *
 * @lphenom-build shared,kphp
 */
final class HttpEmailSender implements CodeSenderInterface
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

        $response  = false;
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


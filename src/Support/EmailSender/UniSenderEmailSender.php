<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support\EmailSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;

/**
 * UniSender email sender — sends email verification codes via UniSender API.
 *
 * Uses UniSender sendEmail API (transactional single email):
 * https://www.unisender.com/ru/support/api/partners/sendemail/
 *
 * Configuration:
 *   - apiKey:      UniSender API key from account settings (API → Key)
 *   - senderEmail: Verified sender email address
 *   - senderName:  Sender display name (e.g. "MyApp")
 *   - subject:     Email subject (default: 'Код подтверждения')
 *   - apiUrl:      Override for non-RU locale endpoints (optional)
 *
 * KPHP-compatible: uses file_get_contents + stream_context_create.
 *
 * @lphenom-build shared,kphp
 */
final class UniSenderEmailSender implements CodeSenderInterface
{
    /** @var string */
    private string $apiKey;

    /** @var string */
    private string $senderEmail;

    /** @var string */
    private string $senderName;

    /** @var string */
    private string $subject;

    /** @var string */
    private string $apiUrl;

    public function __construct(
        string $apiKey,
        string $senderEmail,
        string $senderName,
        string $subject = 'Код подтверждения',
        string $apiUrl = 'https://api.unisender.com/ru/api/sendEmail'
    ) {
        $this->apiKey      = $apiKey;
        $this->senderEmail = $senderEmail;
        $this->senderName  = $senderName;
        $this->subject     = $subject;
        $this->apiUrl      = $apiUrl;
    }

    public function send(string $recipient, string $code): bool
    {
        /** @var array<string, string> $postData */
        $postData = [
            'format'       => 'json',
            'api_key'      => $this->apiKey,
            'email'        => $recipient,
            'sender_name'  => $this->senderName,
            'sender_email' => $this->senderEmail,
            'subject'      => $this->subject,
            'body'         => 'Ваш код подтверждения: ' . $code,
        ];

        $postBody = http_build_query($postData);

        /** @var array<string, string> $httpOpts */
        $httpOpts = [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postBody,
            'timeout' => '10',
        ];

        /** @var array<string, array<string, string>> $opts */
        $opts = ['http' => $httpOpts];

        $context = stream_context_create($opts);

        $response = false;
        try {
            // @ suppresses E_WARNING on connection failure (no-op in KPHP)
            $response = @file_get_contents($this->apiUrl, false, $context);
        } catch (\Throwable $e) {
            return false;
        }

        if ($response === false) {
            return false;
        }

        // UniSender returns {"error":"...", "code":"..."} on failure
        // and {"result":{"email_id":"..."}} on success.
        // A simple substring check avoids json_decode type inference issues in KPHP.
        $errorIdx = strpos($response, '"error"');
        if ($errorIdx !== false) {
            return false;
        }

        return true;
    }
}



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
 * KPHP-compatible: uses curl_* for HTTP POST (file_get_contents with context
 * is not supported in KPHP — it only accepts 1 argument).
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

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        /** @var string[] $headers */
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            return false;
        }

        // UniSender returns {"error":"...", "code":"..."} on failure
        // and {"result":{"email_id":"..."}} on success.
        // A simple substring check avoids json_decode type inference issues in KPHP.
        if (strpos((string) $response, '"error"') !== false) {
            return false;
        }

        return true;
    }
}


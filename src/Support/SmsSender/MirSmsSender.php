<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support\SmsSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;

/**
 * MirSMS sender — sends SMS via mirsms.ru API.
 *
 * Configuration:
 *   - apiUrl: API endpoint (e.g. "https://api.mirsms.ru/message/send")
 *   - login: MirSMS account login
 *   - password: MirSMS account password
 *   - sender: Sender name registered in MirSMS
 *
 * KPHP-compatible: uses curl_* for HTTP POST (file_get_contents with context
 * is not supported in KPHP — it only accepts 1 argument).
 *
 * @lphenom-build shared,kphp
 */
final class MirSmsSender implements CodeSenderInterface
{
    /** @var string */
    private string $apiUrl;

    /** @var string */
    private string $login;

    /** @var string */
    private string $password;

    /** @var string */
    private string $sender;

    public function __construct(
        string $apiUrl,
        string $login,
        string $password,
        string $sender
    ) {
        $this->apiUrl   = $apiUrl;
        $this->login    = $login;
        $this->password = $password;
        $this->sender   = $sender;
    }

    public function send(string $recipient, string $code): bool
    {
        /** @var array<string, string> $postData */
        $postData = [
            'login'    => $this->login,
            'password' => $this->password,
            'sender'   => $this->sender,
            'phone'    => $recipient,
            'text'     => $code,
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

        return true;
    }
}

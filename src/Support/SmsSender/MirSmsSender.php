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
 * KPHP-compatible: uses file_get_contents with stream context.
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
        $message = $code;

        /** @var array<string, string> $postData */
        $postData = [
            'login'    => $this->login,
            'password' => $this->password,
            'sender'   => $this->sender,
            'phone'    => $recipient,
            'text'     => $message,
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
            $response = file_get_contents($this->apiUrl, false, $context);
        } catch (\Throwable $e) {
            return false;
        }

        if ($response === false) {
            return false;
        }

        return true;
    }
}

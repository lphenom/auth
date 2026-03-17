<?php

declare(strict_types=1);

namespace LPhenom\Auth\Support\EmailSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;
use LPhenom\Cache\CacheInterface;

/**
 * Email code authenticator — generates, stores and verifies one-time codes sent via email.
 *
 * Uses lphenom/cache for code storage with TTL.
 * Uses CodeSenderInterface (e.g. SmtpEmailSender) for delivery.
 *
 * KPHP-compatible: no reflection, no callable.
  * @lphenom-build shared,kphp
 */
final class EmailCodeAuthenticator
{
    /** @var CodeSenderInterface */
    private CodeSenderInterface $sender;

    /** @var CacheInterface */
    private CacheInterface $cache;

    /** @var int Code length (digits) */
    private int $codeLength;

    /** @var int Code TTL in seconds */
    private int $codeTtl;

    public function __construct(
        CodeSenderInterface $sender,
        CacheInterface $cache,
        int $codeLength,
        int $codeTtl
    ) {
        $this->sender     = $sender;
        $this->cache      = $cache;
        $this->codeLength = $codeLength;
        $this->codeTtl    = $codeTtl;
    }

    /**
     * Generate a code, store it in cache and send via email.
     *
     * Returns true if the code was sent successfully.
     */
    public function sendCode(string $email): bool
    {
        $code = $this->generateCode();
        $cacheKey = $this->cacheKey($email);

        $codeHash = hash('sha256', $code);
        $this->cache->set($cacheKey, $codeHash, $this->codeTtl);

        return $this->sender->send($email, $code);
    }

    /**
     * Verify a code that was sent to the email address.
     *
     * Returns true if valid, false otherwise. Deletes the code on success.
     */
    public function verifyCode(string $email, string $code): bool
    {
        $cacheKey = $this->cacheKey($email);
        $storedHash = $this->cache->get($cacheKey);

        if ($storedHash === null) {
            return false;
        }

        $inputHash = hash('sha256', $code);
        if (!hash_equals($storedHash, $inputHash)) {
            return false;
        }

        $this->cache->delete($cacheKey);
        return true;
    }

    private function generateCode(): string
    {
        $code = '';
        $length = $this->codeLength > 0 ? $this->codeLength : 6;
        $bytes = random_bytes($length);
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $code .= (string) (ord($bytes[$i]) % 10);
        }
        return $code;
    }

    private function cacheKey(string $email): string
    {
        return 'auth_email_code:' . $email;
    }
}

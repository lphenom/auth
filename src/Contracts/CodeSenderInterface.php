<?php

declare(strict_types=1);

namespace LPhenom\Auth\Contracts;

/**
 * Code sender interface for SMS/Email one-time code authentication.
 *
 * KPHP-compatible: no callable, no reflection.
 */
interface CodeSenderInterface
{
    /**
     * Send a one-time code to the recipient (phone number or email).
     *
     * Returns true on successful delivery, false otherwise.
     */
    public function send(string $recipient, string $code): bool;
}
